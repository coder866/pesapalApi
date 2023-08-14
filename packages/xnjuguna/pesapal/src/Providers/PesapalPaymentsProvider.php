<?php

namespace Xnjuguna\Pesapal\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PesapalPaymentsProvider extends ServiceProvider
{

    /**
     * Register any application services.
     */
    public function register(): void
    {
       $this->registerPublishables();
    }


  /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->loadViewsFrom(__DIR__.'/../views', 'pesapal');
    }

    protected function registerRoutes()
    {
        
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }

    protected function routeConfiguration()
    {
        return [
            'prefix' => config('pesapal.prefix'),
            'middleware' => config('pesapal.middleware'),
        ];
    }

    protected function registerPublishables(){
        $basePath=dirname(__DIR__);

        $arrPublishables=[
            'migrations'=>[
                "$basePath/publishable/database/migrations"=>database_path('migrations'),
            ],
            'config'=>[
                "$basePath/publishable/config/pesapal.php"=>config_path('pesapal.php')
            ],
            ];

            foreach($arrPublishables as $group=>$paths){
                $this->publishes($paths,$group);
            }
    }
}