<?php

namespace App\Filament\Pages;

class Login extends \Filament\Auth\Pages\Login
{
    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            'email' => 'dev@padmission.com',
            'password' => 'password',
            'remember' => true,
        ]);
    }
}
