<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Exception;
use Ryra\Core\App;
use Ryra\Core\Collection\Collection;
use Ryra\Core\HttpClient\Facade\Http;
use Ryra\Core\Log\Log;
use Ryra\Core\Support\Str;
use Ryra\Core\Support\Stringable;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsPassword extends Rule
{
    protected string $message = 'The password :attribute is invalid.';

    public function __construct(protected array $rules = []) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        foreach ($this->rules as $method => $params) {
            $method = 'check' . Str::studly($method);
            $params['value'] = $value;
            if (!App::call([$this, $method], $params)) {
                return false;
            }
        }
        return true;
    }

    public function checkMin(mixed &$value, int &$min, string &$message = ''): bool
    {
        if (\mb_strlen($value) < $min) {
            $this->message = $message;
            return false;
        }
        return true;
    }

    public function checkMax(mixed &$value, int &$max, string &$message = ''): bool
    {
        if (\mb_strlen($value) > $max) {
            $this->message = $message;
            return false;
        }
        return true;
    }

    public function checkLetters(mixed &$value, string &$message = ''): bool
    {
        if (!\preg_match('/\pL/u', $value)) {
            $this->message = $message;
            return false;
        }

        return true;
    }

    public function checkNumbers(mixed &$value, string &$message = ''): bool
    {
        if (!\preg_match('/\pN/u', $value)) {
            $this->message = $message;
            return false;
        }

        return true;
    }

    public function checkSymbols(mixed &$value, string &$message = ''): bool
    {
        if (!\preg_match('/\p{Z}|\p{S}|\p{P}/u', $value)) {
            $this->message = $message;
            return false;
        }

        return true;
    }

    public function checkMixedCase(mixed &$value, string &$message = ''): bool
    {
        if (!\preg_match('/(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})/u', $value)) {
            $this->message = $message;
            return false;
        }

        return true;
    }

    public function checkUncompromised(mixed &$value, int $threshold = 0, string &$message = '')
    {
        if (empty($value = (string) $value)) {
            $this->message = $message;
            return false;
        }

        [$hash, $hashPrefix] = $this->getHash($value);
        $collection = $this->searchPassword($hashPrefix);
        if (!$collection->isEmpty()) {
            $search = $collection
                ->contains(function ($line) use ($hash, $hashPrefix, $threshold) {
                    [$hashSuffix, $count] = explode(':', $line);
    
                    return $hashPrefix.$hashSuffix === $hash && $count > $threshold;
                });

            if ($search) {
                $this->message = $message;
                return false;
            }
        }

        return true;
    }

    /**
     * Get the hash and its first 5 chars.
     *
     */
    protected function getHash(string $value): array
    {
        $hash = \strtoupper(\sha1((string) $value));
        $hashPrefix = \substr($hash, 0, 5);
        return [$hash, $hashPrefix];
    }

    protected function searchPassword($hashPrefix): Collection
    {
        try {
            $response = Http::withHeaders([
                'Add-Padding' => true,
            ])->get("https://api.pwnedpasswords.com/range/$hashPrefix");
        } catch (Exception $e) {
            Log::error($e);
        }

        $body = (isset($response) && $response->successful())
            ? $response->body()
            : '';

        return (new Stringable($body))->trim()->explode("\n")->filter(function ($line) {
            return \str_contains($line, ':');
        });
    }
}