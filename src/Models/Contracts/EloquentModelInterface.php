<?php

namespace Padmission\Tickets\Models\Contracts;

/**
 * Base interface for all Eloquent model interfaces
 * Provides common methods that all Eloquent models should implement
 */
interface EloquentModelInterface
{
    /**
     * Get the model's primary key value
     *
     * @return mixed
     */
    public function getKey();
    
    /**
     * Determine if the model or any of the given attribute(s) have been modified
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function isDirty($attributes = null): bool;
    
    /**
     * Get the original value of an attribute or all original values
     *
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    public function getOriginal($key = null, $default = null);
    
    /**
     * Determine if the given attribute(s) were changed when the model was last saved
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function wasChanged($attributes = null): bool;
}
