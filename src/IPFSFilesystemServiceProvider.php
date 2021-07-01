<?php


namespace gabrielmoura\flysystem_ipfs;

use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Class IPFSFilesystemServiceProvider
 * @package gabrielmoura\flysystem_ipfs
 */
class IPFSFilesystemServiceProvider extends ServiceProvider
{
   public function boot()
    {
        Storage::extend('ipfs', function ($app, $config) {
            return new Filesystem(new Adapter($config));
        });
    }

    public function register()
    {
        //
    }
}