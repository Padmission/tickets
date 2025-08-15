<?php

namespace App\Filament\Pages;

use Filament\Pages\Auth\Login as BasePage;

class Login extends BasePage
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
