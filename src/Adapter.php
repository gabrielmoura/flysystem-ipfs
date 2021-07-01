<?php

namespace gabrielmoura\flysystem_ipfs;

use Illuminate\Support\Facades\Http;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\CanOverwriteFiles;
use League\Flysystem\Config;

//use League\Flysystem\FileExistsException;
//use League\Flysystem\FileNotFoundException;

/**
 * Class Adapter
 * @package gabrielmoura\flysystem_ipfs
 * @author Gabriel Moura <gabriel.all@yandex.com>
 */
class Adapter extends AbstractAdapter implements CanOverwriteFiles
{
    protected $config, $root;
    private $http, $response;
    private $publicGateway = 'https://ipfs.com/ipfs/';



    public function __construct(array $config)
    {
        $this->http = Http::baseUrl($config['host']);
        $this->config = $config;
        $this->root = $config['root'];
        $this->publicGateway = $config['url'];


        // Ensure the existence of the root directory
        $this->root = ltrim($this->root, '/');

        $this->hasRoot();
    }

    /**
     * It will check if the root path exists, otherwise it will create it.
     * @return bool
     */
    private function hasRoot(): bool
    {
        $this->response = $this->http->post('/files/stat?arg=/' . $this->root);
        if ($this->response->failed()) {

            $url = '/files/mkdir?' . http_build_query(['arg' => "/" . $this->root, 'parent' => true]);
            $this->response = $this->http->post($url, []);
        }
        return $this->response->successful();
    }

    /**
     * will correct the path
     * @param $path
     * @return string
     */
    private function correctPath($path): string
    {
        return (isset($this->root)) ? $this->root . '/' . $path : $path;
    }

    /**
     * Creates a new directory on the IPFS node
     *
     * @param string $dirname location of new directory
     * @return bool success status
     */
    private function mkdir(string $dirname)
    {

        $url = '/files/mkdir?' . http_build_query(['arg' => "/" . $this->correctPath($dirname), 'parent' => true]);

        $this->response = $this->http->post($url, []);
        return $this->response->successful(); // return true if the directory was created, false if not
    }

    /**
     * Returns the expected pattern.
     * @param array $entry
     * @param false $root
     * @return array
     */
    private function normalizeFile(array $entry, $root = false)
    {
        if (!!$root) {
            return $this->getMetadata( $root . '/' . $entry['Name']);
        }

        return [
            'type' => ($entry['Type'] == 'directory') ? 'dir' : 'file',
            'path' => $entry['Name'],
            'timestamp' => isset($entry['Mtime']) ? $entry['Mtime'] : time(),
            'size' => ($entry['Size'] == 0) ? ($entry['Type'] == 'directory') ? $entry['CumulativeSize'] : $entry['Size'] : $entry['Size'],
            'visibility' => isset($entry['Mode']) && substr($entry['Mode'], 2) != '00' ? 'public' : 'private',
        ];
    }


    /**
     * Upload a file to IPFS, if it doesn't exist, the path will create one.
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @param false $append
     * @return array|mixed
     * @throws \Exception
     */
    private function upload(string $path, string $contents, Config $config, $append = false)
    {
        $this->response = $this->http->attach('file', file_get_contents($contents), $contents)
            ->post('/add?' . http_build_query([
                    'stream-channels' => true,
                    'pin' => false,
                    'wrap-with-directory' => false
                ]));

        if ($this->response->successful()) {
            $file = $this->response->json();
            $mvToDirectory = $this->http->post('/files/cp?arg=/ipfs/' . $file['Hash'] .
                '&arg=/' . $this->correctPath($path), []);

            if ($mvToDirectory->successful()) {
                return $file;
            } else {
                //Caso não consiga mover para o diretório tentará criar um e mover novamente.
                if ($this->mkdir(str_replace(basename($path), '', $path))) {
                    $mvToDirectory = $this->http->post('/files/cp?arg=/ipfs/' . $file['Hash'] .
                        '&arg=/' . $this->correctPath($path), []);

                    if ($mvToDirectory->successful()) return $file;
                }
            }
        }
        return false;

    }


    /**
     * downloads a file via the IPFS API
     *
     * @param string $path path of the file
     * @return bool|string returns contents of files or false if it failed
     */
    private function download(string $path)
    {
        $this->response = $this->http->post('files/read?arg=/' . $this->correctPath($path));
        return $this->response->body();
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        return $this->getMetadata($path) === false ? false : true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        $response = $this->download($path);
        return ['type' => 'file', 'path' => "{$path}", 'contents' => $response];

    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $response = $this->download($path);
        return ['type' => 'file', 'path' => "{$path}", 'stream' => $response];
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        $result = [];
        $this->response = $this->http->post('files/ls?arg=/' . $this->correctPath($directory));

        foreach ($this->response->json()['Entries'] as $e) $result[] = $this->normalizeFile($e,$directory);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {


        $this->response = $this->http->post('/files/stat?arg=/' . $this->correctPath($path));
        if ($this->response->failed()) return false;

        return $this->normalizeFile(array_merge($this->response->json(), ['Name' => $path]));
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    public function getVisibility($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {

        return $this->upload($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->upload($path, stream_get_contents($resource), $config);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config, true);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->upload($path, stream_get_contents($resource), $config, true);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {

        $this->response = $this->http->post('/files/mv?arg=/' . $this->correctPath($path) . '&arg=/' . $this->correctPath($newpath));
        return $this->response->successful();
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath)
    {
        $this->response = $this->http->post('/files/cp?arg=/' . $this->correctPath($path) . '&arg=/' . $this->correctPath($newpath));
        return $this->response->successful();
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        $this->response = $this->http->post('/files/rm?arg=/' . $this->correctPath($path));
        return $this->response->successful();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname)
    {
        return $this->delete($dirname);
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, Config $config)
    {
        return $this->mkdir($dirname);
    }

    public function setVisibility($path, $visibility)
    {
        $mode = $this->permissions[$this->getMetadata($path)['type']][$visibility];
        $this->callAPI('/files/chmod', ['arg' => $path, 'mode' => sprintf('%o', $mode)]);

    }

    public function getPublicUrl($path)
    {

        $this->response = $this->http->post('/files/stat?arg=/' . $this->correctPath($path));
        if ($this->response->failed()) return false;

        return $this->publicGateway . $this->response->json()['Hash'];
    }

    public function getDirectUrl($path)
    {
        return $this->getPublicUrl($path);
    }

    public function getUrl($path)
    {
        return $this->getPublicUrl($path);
    }
}