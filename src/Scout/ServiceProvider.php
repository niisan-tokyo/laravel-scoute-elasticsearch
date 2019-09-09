<?php
namespace Niisan\Laravel\Scout;

use Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider as BaseProvider;
use Laravel\Scout\EngineManager;
use Niisan\Laravel\Scout\Engines\ElasticSearchEngine;

class ServiceProvider extends BaseProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        resolve(EngineManager::class)->extend('elasticsearch', function () {
            $config = config('scout.elasticsearch');
            return new ElasticSearchEngine(
                ClientBuilder::create()->setHosts($config['hosts'])->build()
            );
        });
    }
}