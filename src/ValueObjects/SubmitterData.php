<?php

namespace Padmission\Tickets\ValueObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Livewire\Wireable;

final readonly class SubmitterData implements Castable, Wireable
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes)
            {
                $data = json_decode($value, true);

                if ($data === null) {
                    return null;
                }

                return new SubmitterData(
                    name: data_get($data, 'name'),
                    email: data_get($data, 'email'),
                );
            }

            public function set(Model $model, string $key, mixed $value, array $attributes)
            {
                if ($value instanceof SubmitterData) {
                    return [
                        'submitter_data' => json_encode([
                            'name' => $value->name,
                            'email' => $value->email,
                        ]),
                    ];
                }

                return ['submitter_data' => null];
            }
        };
    }

    public function toLivewire()
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    public static function fromLivewire($value)
    {
        if (is_array($value)) {
            return new SubmitterData(
                name: data_get($value, 'name'),
                email: data_get($value, 'email'),
            );
        }

        return null;
    }
}
