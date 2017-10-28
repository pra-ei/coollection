<?php

namespace PragmaRX\Coollection\Package;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\HigherOrderCollectionProxy;
use JsonSerializable;
use Tightenco\Collect\Contracts\Support\Arrayable;
use Tightenco\Collect\Contracts\Support\Jsonable;
use Tightenco\Collect\Support\Collection as TightencoCollection;
use Traversable;

class Coollection extends TightencoCollection
{
    const NOT_FOUND = '!__NOT__FOUND__!';

    static $raiseExceptionOnNull = true;

    /**
     * Take the first item.
     *
     * @param callable|null $callback
     * @param null $default
     * @return mixed|static
     */
    public function first(callable $callback = null, $default = null)
    {
        return $this->wrap(parent::first($callback, $default));
    }

    /**
     * Get an item from the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->wrapIfArrayable(parent::get($key, $default));
    }

    private function getArrayKey($key)
    {
        if (array_key_exists($key, $this->items)) {
            return $key;
        }

        $value = $this->keys()->mapWithKeys(function($item) {
            return [snake($item) => $item];
        })->get($key);

        return is_null($value)
            ? static::NOT_FOUND
            : $value;
    }

    private function getByPropertyName($key)
    {
        if (($key = $this->getArrayKey($key)) !== static::NOT_FOUND) {
            if (is_array($this->items[$key])) {
                return $this->wrap($this->items[$key]);
            }

            return $this->items[$key];
        }

        return static::NOT_FOUND;
    }

    /**
     * @return bool
     */
    public static function shouldRaiseExceptionOnNull()
    {
        return self::$raiseExceptionOnNull;
    }

    /**
     * Get and remove the last item from the collection.
     *
     * @return mixed|static
     */
    public function pop()
    {
        return $this->wrap(parent::pop());
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param  callable  $callback
     * @param  mixed  $initial
     * @return mixed|static
     */
    public function reduce(callable $callback, $initial = null)
    {
        return $this->wrap(parent::reduce($callback, $initial));
    }

    /**
     * @param bool $raiseExceptionOnNull
     */
    public static function setRaiseExceptionOnNull(bool $raiseExceptionOnNull)
    {
        self::$raiseExceptionOnNull = $raiseExceptionOnNull;
    }

    /**
     * Get and remove the first item from the collection.
     *
     * @return mixed|static
     */
    public function shift()
    {
        return $this->wrapIfArrayable(parent::shift());
    }

    /**
     * Dynamically access collection proxies.
     *
     * @param  string  $key
     * @return mixed|static
     *
     * @throws \Exception
     */
    public function __get($key)
    {
        if (property_exists($this, $key)) {
            return $this->{$key};
        }

        if (($value = $this->getByPropertyName($key)) !== static::NOT_FOUND) {
            return $value;
        }

        if (!in_array($key, static::$proxies)) {
            if (static::$raiseExceptionOnNull) {
                throw new Exception("Property [{$key}] does not exist on this collection instance.");
            }

            return null;
        }

        return new HigherOrderCollectionProxy($this, $key);
    }

    /**
     * @param string $key
     * @param mixed $operator
     * @param null $value
     * @return static
     */
    public function where($key, $operator, $value = null)
    {
        if (func_num_args() == 2) {
            $value = $operator;

            $operator = '=';
        }

        if (method_exists($this, $method = 'where'.ucfirst($key))) {
            return $this->{$method}($value);
        }

        return $this->wrap(parent::where($key, $operator, $value));
    }

    public function whereLanguage($value)
    {
        return $this->_whereAttribute('languages', $value);
    }

    public function whereISO639_3($value)
    {
        return $this->_whereKey('languages', $value);
    }

    public function whereISO4217($value)
    {
        return $this->_whereAttribute('currency', $value);
    }

    private function _whereAttribute(string $arrayName, $value)
    {
        return $this->filter(function ($data) use ($value, $arrayName) {
            if (isset($data->{$arrayName})) {
                return in_array($value, (array) $data->{$arrayName});
            }

            return false;
        });
    }

    private function _whereKey(string $arrayName, $value)
    {
        return $this->filter(function ($data) use ($value, $arrayName) {
            if (isset($data->{$arrayName})) {
                return Arr::has($data->{$arrayName}, $value);
            }

            return false;
        });
    }

    /**
     * Check if value is arrayable
     *
     * @param  mixed  $items
     * @return bool
     */
    protected function isArrayable($items)
    {
        return
            is_array($items) ||
            $items instanceof self ||
            $items instanceof Arrayable ||
            $items instanceof Jsonable ||
            $items instanceof JsonSerializable ||
            $items instanceof Traversable
        ;
    }

    /**
     * Wrap on static if the value is arrayable.
     *
     * @param $value
     * @return static
     */
    private function wrapIfArrayable($value)
    {
        return $this->isArrayable($value)
            ? $this->wrap($value)
            : $value;
    }

    /**
     * Get the last item from the collection.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed|static
     */
    public function last(callable $callback = null, $default = null)
    {
        return $this->wrapIfArrayable(parent::last($callback, $default));
    }

    /**
     * Get one or a specified number of items randomly from the collection.
     *
     * @param  int|null  $number
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function random($number = null)
    {
        return $this->wrapIfArrayable(parent::random($number));
    }

    /**
     * Get the mode of a given key.
     *
     * @param  mixed  $key
     * @return mixed|static
     */
    public function mode($key = null)
    {
        return $this->wrapIfArrayable(parent::mode($key));
    }

    /**
     * ORIGINAL IS BROKEN IN LARAVEL
     * PR: https://github.com/laravel/framework/pull/21854#issuecomment-340220246
     */
    public function unique($key = null, $strict = false)
    {
        $callback = $this->valueRetriever($key);

        $exists = [];

        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }
}