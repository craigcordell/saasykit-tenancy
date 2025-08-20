<?php

namespace App\Livewire\Checkout;

use App\Exceptions\LoginException;
use App\Exceptions\NoPaymentProvidersAvailableException;
use App\Models\User;
use App\Services\LoginService;
use App\Services\PaymentProviders\PaymentService;
use App\Services\UserService;
use App\Validator\LoginValidator;
use App\Validator\RegisterValidator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class CheckoutForm extends Component
{
    public $intro;

    public $name;

    public $email;

    public $password;

    public $paymentProvider;

    public $recaptcha;

    protected $paymentProviders = [];

    public function mount(string $intro = '')
    {
        $this->intro = $intro;
    }

    public function render(PaymentService $paymentService)
    {
        return view('livewire.checkout.checkout-form', [
            'userExists' => $this->userExists($this->email),
            'paymentProviders' => $this->getPaymentProviders($paymentService),
        ]);
    }

    public function handleLoginOrRegistration(
        LoginValidator $loginValidator,
        RegisterValidator $registerValidator,
        UserService $userService,
        LoginService $loginService,
    ) {
        if (! auth()->check()) {
            if ($this->userExists($this->email)) {
                $this->loginUser($loginValidator, $loginService);
            } else {
                $this->registerUser($registerValidator, $userService);
            }
        }

        $user = auth()->user();
        if (! $user) {
            $this->redirect(route('login'));
        }

        if ($user->is_blocked) {
            auth()->logout();
            throw ValidationException::withMessages([
                'email' => __('Your account is blocked, please contact support.'),
            ]);
        }

    }

    protected function loginUser(LoginValidator $loginValidator, LoginService $loginService)
    {
        $fields = [
            'email' => $this->email,
            'password' => $this->password,
        ];

        if (config('app.recaptcha_enabled')) {
            $fields[recaptchaFieldName()] = $this->recaptcha;
        }

        $validator = $loginValidator->validate($fields);

        if ($validator->fails()) {
            $this->resetReCaptcha();
            throw new ValidationException($validator);
        }

        try {
            $result = $loginService->attempt([
                'email' => $this->email,
                'password' => $this->password,
            ], true);
        } catch (Throwable $e) {  // usually thrown when 2FA is enabled so user need to be redirected to login page to enter 2FA code
            throw new LoginException;
        }

        if (! $result) {
            $this->resetReCaptcha();
            throw ValidationException::withMessages([
                'email' => __('Wrong email or password'),
            ]);
        }
    }

    protected function registerUser(RegisterValidator $registerValidator, UserService $userService)
    {
        $fields = [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ];

        if (config('app.recaptcha_enabled')) {
            $fields[recaptchaFieldName()] = $this->recaptcha;
        }

        $validator = $registerValidator->validate($fields, false);

        if ($validator->fails()) {
            $this->resetReCaptcha();
            throw new ValidationException($validator);
        }

        $user = $userService->createUser([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        auth()->login($user);

        $user->sendEmailVerificationNotification();

        return $user;
    }

    protected function userExists(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        return User::where('email', $email)->exists();
    }

    protected function getPaymentProviders(PaymentService $paymentService)
    {
        if (count($this->paymentProviders) > 0) {
            return $this->paymentProviders;
        }

        $this->paymentProviders = $paymentService->getActivePaymentProviders(true);

        if (empty($this->paymentProviders)) {
            logger()->error('No payment providers available');

            throw new NoPaymentProvidersAvailableException('No payment providers available');
        }

        if ($this->paymentProvider === null) {
            $this->paymentProvider = $this->paymentProviders[0]->getSlug();
        }

        return $this->paymentProviders;
    }

    protected function resetReCaptcha()
    {
        $this->dispatch('reset-recaptcha');
    }
}
