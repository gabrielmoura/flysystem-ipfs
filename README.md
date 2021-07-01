# IPFS Flysystem Laravel
![](https://camo.githubusercontent.com/843fdef276455e6653a9a28a115a6810386b98c6158f828b93ea7add06da46cc/68747470733a2f2f697066732e696f2f697066732f516d514a363850464d4464417367435a76413155567a7a6e3138617356636637485676434467706a695343417365)

Este pacote tenta integrar o Laravel ao IPFS.
Caso veja alguma melhoria não hesite em falar.

---
This package tries to integrate Laravel with IPFS.
If you see any improvement, please don't hesitate to talk.

config/filesystems.php:
```php
'ipfs' => [
            'driver' => 'ipfs',
            'host' => env('IPFS_ADDRESS', 'http://127.0.0.1:5001/api/v0'),
            'root' => env('IPFS_ROOT', '/laravel'),
            'url' => env('IPFS_URL', 'https://cloudflare-ipfs.com/ipfs/'),
        ],
``` 
