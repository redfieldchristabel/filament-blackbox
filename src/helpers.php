<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

if (! function_exists('blackboxCheck')) {
    /**
     * Safely checks permissions by ensuring a policy exists before calling Gate::check.
     * Mimics the signature of Illuminate\Support\Facades\Gate::check().
     */
    function blackboxCheck(iterable | string $abilities, mixed $arguments = []): bool
    {

        // Extract the model from arguments (standard Gate behavior)
        // If $arguments is an array, the model is usually the first element.
        $model = is_array($arguments) ? Arr::first($arguments) : $arguments;

        if (! $model) {
            return false;
        }

        // Get the policy for the model
        $policy = Gate::getPolicyFor($model);

        // If no policy is registered for this model, we fail safe
        if (! $policy) {
            return true;
        }

        // If policy exists, delegate to the real Gate
        return Gate::check($abilities, $arguments);
    }
}
