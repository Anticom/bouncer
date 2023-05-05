<?php

namespace Silber\Bouncer;

use Silber\Bouncer\Database\Models;

use App\User;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class Helpers
{
    /**
     * Ensure that the given logical operator is 'and'|'or'.
     *
     * @param  string  $operator
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public static function ensureValidLogicalOperator($operator)
    {
        if (! in_array($operator, ['and', 'or'])) {
            throw new InvalidArgumentException(
                "{$operator} is an invalid logical operator"
            );
        }
    }

    /**
     * Extract the model instance and model keys from the given parameters.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection|string  $model
     * @param  array|null  $keys
     * @return array
     */
    public static function extractModelAndKeys($model, array $keys = null)
    {
        if (! is_null($keys)) {
            if (is_string($model)) {
                $model = new $model;
            }

            return [$model, $keys];
        }

        if ($model instanceof Model) {
            return [$model, [$model->getKey()]];
        }

        if ($model instanceof Collection) {
            $keys = $model->map(function ($model) {
                return $model->getKey();
            });

            return [$model->first(), $keys];
        }
    }

    /**
     * Fill the given array with the given value for any missing keys.
     *
     * @param  iterable  $array
     * @param  mixed  $value
     * @param  iterable  $keys
     * @return iterable
     */
    public static function fillMissingKeys($array, $value, $keys)
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $array)) {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Group models and their identifiers by type (models, strings & integers).
     *
     * @param  iterable  $models
     * @return array
     */
    public static function groupModelsAndIdentifiersByType($models)
    {
        $groups = (new Collection($models))->groupBy(function ($model) {
            if (is_numeric($model)) {
                return 'integers';
            } else if (is_string($model)) {
                return 'strings';
            } else if ($model instanceof Model) {
                return 'models';
            }

            throw new InvalidArgumentException('Invalid model identifier');
        })->map(function ($items) {
            return $items->all();
        })->all();

        return static::fillMissingKeys($groups, [], ['integers', 'strings', 'models']);
    }

    /**
     * Determines if an array is associative.
     *
     * An array is "associative" if it doesn't have sequential numerical keys beginning with zero.
     *
     * @param  mixed  $array
     * @return bool
     */
    public static function isAssociativeArray($array)
    {
        if (! is_array($array)) {
            return false;
        }

        $keys = array_keys($array);

        return array_keys($keys) !== $keys;
    }

    /**
     * Determines if an array is numerically indexed.
     *
     * @param  mixed  $array
     * @return bool
     */
    public static function isIndexedArray($array)
    {
        if (! is_array($array)) {
            return false;
        }

        foreach ($array as $key => $value) {
            if (! is_numeric($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines whether the given model is set to soft delete.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public static function isSoftDeleting(Model $model)
    {
        // Soft deleting models is controlled by adding the SoftDeletes trait
        // to the model. Instead of recursively looking for that trait, we
        // will check for the existence of the `isForceDeleting` method.
        if (! method_exists($model, 'isForceDeleting')) {
            return false;
        }

        return ! $model->isForceDeleting();
    }

    /**
     * Convert the given value to an array.
     *
     * @param  mixed  $value
     * @return array
     */
    public static function toArray($value)
    {
        if (is_array($value)) {
            return self::unwrapEnums($value);
        }

        if ($value instanceof Collection) {
            return self::unwrapEnums($value->all());
        }

        return [self::unwrapEnum($value)];
    }

    /**
     * Map a list of authorities by their class name.
     *
     * @param  array  $authorities
     * @return array
     */
    public static function mapAuthorityByClass(array $authorities)
    {
        $map = [];

        foreach ($authorities as $authority) {
            if ($authority instanceof Model) {
                $map[get_class($authority)][] = $authority->getKey();
            } else {
                $map[Models::classname(User::class)][] = $authority;
            }
        }

        return $map;
    }

    /**
     * Partition the given collection into two collection using the given callback.
     *
     * @param  iterable  $items
     * @param  callable  $callback
     * @return \Illuminate\Support\Collection
     */
    public static function partition($items, callable $callback)
    {
        $partitions = [new Collection, new Collection];

        foreach ($items as $key => $item) {
            $partitions[(int) ! $callback($item, $key)][$key] = $item;
        }

        return new Collection($partitions);
    }

    /**
     * Extract the value of a backed enum, pass through string otherwise.
     *
     * @param string|\BackedEnum $enum
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function unwrapEnum($enum)
    {
        // If we get passed in a string, we don't need to do anything to it
        if(is_string($enum)) {
            return $enum;
        }

        // PHP version gate
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            throw new InvalidArgumentException("Only strings are supported for this PHP version");
        }

        // The only other supported variant are BackedEnums now
        if(!$enum instanceof \BackedEnum) {
            throw new  InvalidArgumentException("Only strings and BackedEnums are supported");
        }

        // Make sure the backing type is string
        $enumReflection = new \ReflectionEnum($enum);
        if($enumReflection->getBackingType()->getName() !== 'string') {
            throw new InvalidArgumentException("The enum must be backed by strings");
        }

        // Unwrap the value
        return $enum->value;
    }

    /**
     * Extract the value of backed enums, pass through strings.
     *
     * @param string[]|\BackedEnum[]||string|\BackedEnum $enums
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public static function unwrapEnums($enums)
    {
        return array_map([self::class, 'unwrapEnum'], (array) $enums);
    }
}
