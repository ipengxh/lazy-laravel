<?php
declare(strict_types=1);

namespace LazyLaravel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Whoops\Exception\ErrorException;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $modelClass;

    private $modelInstance;

    protected $viewPath;

    protected $routerPrefix;

    protected $paginateForIndex = true;

    protected $order = 'DESC';

    protected $orderBy = 'id';

    protected $filterKeyMap = [
        'is' => '=',
        'not' => '!=',
        'like' => 'like',
        'not_like' => 'not like',
        'lt' => '<',
        'lte' => '<=',
        'gt' => '>',
        'gte' => '>=',
        'between' => 'between',
        'null' => 'null',
        'in' => 'in',
        'not_in' => 'not in'
    ];

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Whoops\Exception\ErrorException
     */
    public function index(Request $request)
    {
        $model = $this->getModel();
        if ($request->query_key and $request->query_value) {
            $queryRules = explode('-', $request->query_key);
            $model = $model->where($queryRules[0], $queryRules[1] ?? '=', $request->query_value);
        }
        foreach ($request->all() as $requestKey => $requestValue) {
            if ('page' !== $requestKey and 'query_key' !== $requestKey and 'query_value' !== $requestKey) {
                $model = $this->filter($model, $requestKey, $requestValue);
            }
        }
        if ($this->order) {
            $model = $model->orderBy($this->orderBy, $this->order);
        }
        $variableName = $this->getVariableName(true);
        $$variableName = $this->paginateForIndex ? $model->paginate() : $model->get();
        return view($this->getViewPath() . '.index', compact($variableName));
    }

    /**
     * @param Model|Builder $model
     * @param string $requestKey
     * @param string $requestValue
     * @return Builder|Model
     * @throws \Whoops\Exception\ErrorException
     */
    protected function filter($model, string $requestKey, ?string $requestValue)
    {
        [$column, $operator, $relation] = $this->parseRequestKey($requestKey);
        return $this->makeBuilder($model, $column, $requestValue, $relation, $operator);
    }

    /**
     * @param Model|Builder $model
     * @param string $column
     * @param string $requestValue
     * @param null|string $relation
     * @param string $operator
     * @return mixed
     */
    protected function makeBuilder($model, string $column, ?string $requestValue, ?string $relation, string $operator)
    {
        if (null == $requestValue) {
            return $model;
        }
        $value = $this->transformRequestValue($operator, $requestValue);
        $operator = strtoupper($this->filterKeyMap[$operator]);
        if ('NULL' === $operator) {
            if (!$value) {
                $operator = null;
            } elseif ("IS" == strtoupper($value)) {
                $operator = "IS NULL";
            } elseif ("NOT" == strtoupper($value)) {
                $operator = "IS NOT NULL";
            } elseif ('ALL' == strtoupper($value)) {
                $operator = null;
            }
        }
        if ($relation) {
            $model = $model->whereHas($relation, function (Builder $builder) use ($column, $value, $operator, $model) {
                if (starts_with($column, '_')) {
                    $column = str_replace_first('_', '', $column);
                    $builder->where(function (Builder $builder) use ($column, $operator, $value, $model) {
                        if ($model instanceof Builder) {
                            $model = $model->getModel();
                        }
                        $builder->where($column, $operator, $value)
                            ->orWhere($model->getKeyName(), trim($value, '%'));
                    });
                } else {
                    if ('IN' == $operator) {
                        $builder->whereIn($column, $value);
                    } else {
                        $builder->where($column, $operator, $value);
                    }
                }
            });
        } else {
            if ("IS NULL" === $operator) {
                if ('deleted_at' == $column) {
                    // do nothing
                } else {
                    $model = $model->whereNull($column);
                }
            } elseif ("IS NOT NULL" === $operator) {
                if ('deleted_at' == $column) {
                    $model = $model->onlyTrashed();
                } else {
                    $model = $model->whereNotNull($column);
                }
            } elseif (null === $operator) {
                if ('deleted_at' == $column) {
                    $model = $model->withTrashed();
                }
            } else {
                if (starts_with($column, '_')) {
                    $column = str_replace_first('_', '', $column);
                    $model = $model->where(function (Builder $builder) use ($column, $operator, $value, $model) {
                        $builder->where($column, $operator, $value)
                            ->orWhere($model->getKeyName(), trim($value, '%'));
                    });
                } else {
                    if ('IN' == $operator) {
                        $model = $model->whereIn($column, $value);
                    } else {
                        $model = $model->where($column, $operator, $value);
                    }
                }
            }
        }
        return $model;
    }

    protected function transformRequestValue(string $operator, ?string $requestValue)
    {
        switch ($this->filterKeyMap[$operator]) {
            case '=':
            case '!=':
            case '<':
            case '<=':
            case '>':
            case '>=':
            case 'null':
                $value = $requestValue;
                break;
            case 'like':
            case 'not like':
                $value = "%{$requestValue}%";
                break;
            case 'between':
            case 'in':
            case 'not in':
                $value = explode(',', $requestValue);
                break;
            default:
                $value = $requestValue;
        }
        return $value;
    }

    /**
     * @param string $requestKey
     * @return array
     * @throws \Whoops\Exception\ErrorException
     */
    private function parseRequestKey(string $requestKey): array
    {
        $keys = explode('-', $requestKey);
        $operator = array_last($keys);
        if (!in_array($operator, array_keys($this->filterKeyMap))) {
            $operator = 'is';
            $keys[] = $operator;
        }
        if (!isset($this->filterKeyMap[$operator])) {
            throw new ErrorException("Whoops, operation {$operator} is now allowed.");
        }
        array_pop($keys);
        $column = array_pop($keys);
        if (!empty($keys)) {
            $relation = implode('.', $keys);
        } else {
            $relation = null;
        }
        return [$column, $operator, $relation];
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Whoops\Exception\ErrorException
     */
    public function show($id)
    {
        $variableName = $this->getVariableName();
        $$variableName = $this->getModel()->findOrFail($id);
        return view($this->getViewPath() . '.show', compact($variableName));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Whoops\Exception\ErrorException
     */
    public function create()
    {
        return view($this->getViewPath() . '.create');
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Whoops\Exception\ErrorException
     */
    public function store(Request $request)
    {
        $this->getModel()->fill($request->all())->save();
        return redirect(route($this->routerPrefix . $this->getViewPath() . '.index'))->with(['success' => '保存成功']);
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Whoops\Exception\ErrorException
     */
    public function edit($id)
    {
        $variableName = $this->getVariableName();
        $$variableName = $this->getModel()->findOrFail($id);
        return view($this->getViewPath() . '.edit', compact($variableName));
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Whoops\Exception\ErrorException
     */
    public function update(Request $request, $id)
    {
        $model = $this->getModel();
        $modelInstance = $model->findOrFail($id);
        $modelInstance->fill($request->all())->save();
        return redirect(route($this->routerPrefix . $this->getViewPath() . '.index'))->with(['success' => '更新成功']);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Whoops\Exception\ErrorException
     * @throws \Exception
     */
    public function destroy($id)
    {
        $this->getModel()->findOrFail($id)->delete();
        return redirect(route($this->routerPrefix . $this->getViewPath() . '.index'))->with(['success' => '删除成功']);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Whoops\Exception\ErrorException
     */
    public function restore($id)
    {
        $this->getModel()->onlyTrashed()->findOrFail($id)->restore();
        return redirect(route($this->routerPrefix . $this->getViewPath() . '.index'))->with(['success' => '恢复成功']);
    }

    /**
     * @param bool $newInstance
     * @return \Illuminate\Foundation\Application|mixed|Model|Builder
     * @throws \Whoops\Exception\ErrorException
     */
    protected function getModel($newInstance = false): Model
    {
        if ($newInstance) {
            if ($this->modelClass) {
                return app($this->modelClass);
            } else {
                return $this->guessModel();
            }
        } else {
            return $this->modelInstance = $this->modelInstance ?: $this->getModel(true);
        }
    }

    /**
     * @return \Illuminate\Foundation\Application|mixed
     * @throws \Whoops\Exception\ErrorException
     */
    protected function guessModel(): Model
    {
        $controllerNamespace = $this->getControllerNamespaceShortName();
        $guessModelNamespaceName = str_replace_last('Controller', '', $controllerNamespace);
        $guessModelNamespace = "\\App\\Models\\" . $guessModelNamespaceName;
        return app($guessModelNamespace);
    }

    /**
     * @param bool $plural
     * @return string
     * @throws \Whoops\Exception\ErrorException
     */
    protected function getVariableName($plural = false): string
    {
        $variableName = lcfirst($this->getControllerNamespaceShortName());
        return $plural ? str_plural($variableName) : $variableName;
    }

    /**
     * @return string
     * @throws \Whoops\Exception\ErrorException
     */
    protected function getControllerNamespaceShortName()
    {
        $controllerNamespace = $this->getControllerNamespaceName();
        return str_replace_last('Controller', '', $controllerNamespace);
    }

    /**
     * @throws \Whoops\Exception\ErrorException
     */
    protected function getControllerNamespaceName()
    {
        try {
            return (new \ReflectionClass($this))->getShortName();
        } catch (\ReflectionException $e) {
            throw new ErrorException("Whoops, could not get controller namespace");
        }
    }

    /**
     * @return mixed
     * @throws \Whoops\Exception\ErrorException
     */
    protected function getViewPath()
    {
        if ($this->viewPath) {
            return $this->viewPath;
        }
        $variableName = $this->getVariableName();
        return str_replace('_', '.', snake_case($variableName));
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $file = $request->file('file');
        $stored = $file->storePublicly("public");
        return response()->json(str_replace_first('public', '/storage', $stored));
    }
}