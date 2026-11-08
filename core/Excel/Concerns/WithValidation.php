<?php

namespace Ryra\Core\Excel\Concerns;

interface WithValidation
{
    /**
     * Return validation rules for each row (keyed by column index or heading name).
     *
     * Example:
     *   return [
     *       'name'  => 'required|string|max:255',
     *       'email' => 'required|email',
     *       '0'     => 'required',  // column index when no heading row
     *   ];
     *
     * @return array<string, string|array>
     */
    public function rules(array $data): array;

    /**
     * Return custom validation error messages.
     *
     * @return array<string, string>
     */
    public function customValidationMessages(): array;
}
