<div class="flex-none gap-2 text-primary-500">
    <div class="dropdown dropdown-end">
        <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
            <div class="w-8 rounded-full bg-primary-50">
                <div class="flex flex-row justify-center items-center h-full">
                    <div class="text-2xl font-bold text-primary-500 capitalize">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <ul tabindex="0" class="mt-3 z-1 py-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-52">
                <li class="py-1">
                    @if (auth()->user()->isAdmin())
                        <x-link href="{{ route('filament.admin.pages.dashboard') }}" class="px-2!">
                            <div class="flex flex-row gap-1">
                                @svg('dashboard', 'h-4 text-primary-500 m-1 stroke-primary-500')
                                {{ __('Admin Panel') }}
                            </div>
                        </x-link>
                    @endif
                    <x-link href="{{ route('filament.dashboard.pages.dashboard') }}" class="px-2!">
                        <div class="flex flex-row gap-1">
                            @svg('dashboard', 'h-4 text-primary-500 m-1 stroke-primary-500')
                            {{ __('Dashboard') }}
                        </div>
                    </x-link>
                </li>
                <li class="">
                    <x-link href="{{ route('logout') }}" class="px-2!" onclick="event.preventDefault(); this.closest('form').submit();">
                        <div class="flex flex-row gap-1">
                            @svg('logout', 'h-4 m-1 stroke-primary-500')
                            {{ __('Logout') }}
                        </div>
                    </x-link>
                </li>
            </ul>
        </form>
    </div>
</div>

