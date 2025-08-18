@guest()
    <div class="mb-4">

        <x-heading.h2 class="text-primary-900 text-xl!">
            {{ __('Enter your details') }}
        </x-heading.h2>

        <div class="relative rounded-2xl border border-neutral-200 mt-4 overflow-hidden p-6">

            @if (!empty($intro))
                <div class="mb-4 text-sm">
                    {{ $intro }}
                </div>
            @endif

            <div class="absolute top-0 right-0 p-2">
                    <span wire:loading>
                        <span class="loading loading-spinner loading-xs"></span>
                    </span>
            </div>


            <fieldset class="fieldset">
                <legend class="fieldset-legend font-medium">{{ __('Email Address') }}</legend>
                <input type="email" class="input w-full" name="email" required id="email" wire:model.blur="email" value="{{ old('email') }}" />
            </fieldset>

            @error('email')
            <span class="text-xs text-red-500" role="alert">
                        {{ $message }}
                    </span>
            @enderror


            @if(!empty($email))
                <fieldset class="fieldset">
                    <legend class="fieldset-legend font-medium">{{ __('Password') }}</legend>
                    <input type="password" class="input w-full" name="password" required id="password" wire:model="password" />
                </fieldset>

                @error('password')
                <span class="text-xs text-red-500 ms-1" role="alert">
                            {{ $message }}
                        </span>
                @enderror
            @endif

            @if ($userExists)
                <div class="my-2 ms-1 text-xs text-neutral-400">{{ __('You are already registered, enter your password.') }}</div>
            @elseif(!empty($email))
                <div class="my-2 ms-1 text-xs text-neutral-400">{{ __('Enter a password for your new account.') }}</div>
            @endif

            @if($userExists)
                @if (Route::has('password.request'))
                    <div class="text-end">
                        <a class="text-primary-500 text-xs" href="{{ route('password.request') }}">
                            {{ __('Forgot Your Password?') }}
                        </a>
                    </div>
                @endif
            @endif


            @if(!$userExists || empty($email))

                <fieldset class="fieldset">
                    <legend class="fieldset-legend font-medium">{{ __('Your Name') }}</legend>
                    <input type="text" class="input w-full" name="name" required id="name" wire:model="name" value="{{ old('name') }}" />
                </fieldset>

                @error('name')
                    <span class="text-xs text-red-500" role="alert">
                        {{ $message }}
                    </span>
                @enderror
            @endif


            @if (config('app.recaptcha_enabled'))

                <div wire:ignore>
                    <input type="text" wire:model="recaptcha" x-on:captcha-success.window="$wire.recaptcha = $event.detail.token" hidden>
                    <div class="my-4">
                        {!! htmlFormSnippet([
                            "callback" => "onRecaptchaSuccess"
                        ]) !!}
                    </div>
                </div>

                @error('g-recaptcha-response')
                <span class="text-xs text-red-500" role="alert">
                                {{ $message }}
                            </span>
                @enderror

            @endif

            @push('tail')
                <script>

                    function onRecaptchaSuccess(token) {
                        Livewire.dispatch('captcha-success', { token: token });
                    }

                    document.addEventListener('livewire:initialized', () => {
                        Livewire.on('reset-recaptcha', (event) => {
                            // wait .5 seconds before resetting the recaptcha
                            setTimeout(() => {
                                grecaptcha.reset();
                            }, 500);
                        });
                    });

                </script>
            @endpush

            @push('tail')
                {!! htmlScriptTagJsApi() !!}
            @endpush

            @if(empty($email))
                <x-auth.social-login>
                    <x-slot name="before">
                        <div class="flex flex-col w-full">
                            <div class="divider">{{ __('or') }}</div>
                        </div>
                    </x-slot>
                </x-auth.social-login>
            @endif

        </div>
    </div>

@endguest
