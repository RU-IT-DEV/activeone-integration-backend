<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('has-access', function (User $user, string $resource) {
            $navigations = $user->role->navigations;
            
            $act = explode("-", $resource);
            if (count($act) > 1) {
                $navigation_name = $act[0];
                $ability = $act[1];
                $filtered_nav = array_filter(json_decode($navigations, true), function ($navigation) use ($navigation_name) {
                    if (isset($navigation['navigation_name'])) {
                        return $navigation['navigation_name'] == $navigation_name;
                    }
                    return false;
                });
                
                $filtered_nav_actions = array_values($filtered_nav)[0]['actions'];
    
                return in_array($ability, $filtered_nav_actions);
            } else {
                return true;
            }
        });
    }
}
