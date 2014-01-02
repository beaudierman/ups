<?php namespace Beaudierman\Ups;

use Illuminate\Support\ServiceProvider;

class UpsServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('beaudierman/ups');

		$app = $this->app;

		$this->app->before(function() use ($app)
		{
			$app['ups']->loadCredentials($app['config']->get('ups::credentials'));
		});
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['ups'] = $this->app->share(function($app)
		{
			return new Ups;
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('ups');
	}

}