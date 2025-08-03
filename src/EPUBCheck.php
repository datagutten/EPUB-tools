<?php

namespace datagutten\epub;

use datagutten\tools\files\files;
use dependcheck;
use DependencyFailedException;
use Symfony\Component\Process\Process;
use WpOrg\Requests\Requests;
use Symfony\Component\Filesystem\Filesystem;

class EPUBCheck
{
    public static string $jar_path;

    public static function download_jar(): void
    {
        self::$jar_path = files::path_join(__DIR__, 'epubcheck.jar');
        if (file_exists(self::$jar_path))
            return;

        $response = Requests::get('https://api.github.com/repos/w3c/epubcheck/releases/latest');
        $release = $response->decode_body();
        $url = $release['assets'][0]['browser_download_url'];
        $zip_file = basename($url);
        $filesystem = new Filesystem();
        if (!file_exists($zip_file))
        {
            $response = Requests::get($url, options: ['filename' => $zip_file]);
            $response->throw_for_status();
        }
        $unpack_folder = files::path_join(__DIR__, 'epubcheck');
        $process = new Process(['unzip', $zip_file, '-d', $unpack_folder]);
        $process->mustRun();

        $filesystem->rename(files::path_join($unpack_folder, basename($url, '.zip'), 'epubcheck.jar'), self::$jar_path, true);
        $filesystem->remove($unpack_folder);
        $filesystem->remove($zip_file);
    }

    protected static function epubCheckJava($file): Process
    {
        self::download_jar();
        $process = new Process(['java', '-jar', self::$jar_path, $file, '-j', '-']);
        $process->mustRun();
        return $process;
    }

    protected static function epubCheckDocker(string $file): Process
    {
        $process_images = new Process(['docker', 'image', 'ls', '--format', 'json']);
        $process_images->mustRun();
        if (!str_contains($process_images->getOutput(), '"Repository":"epubcheck"'))
        {
            $process_build = new Process(['docker', 'build', 'https://github.com/w3c/epubcheck.git', '-t', 'epubcheck']);
            $process_build->setTimeout(null);
            $process_build->mustRun();
        }

        $pathinfo = pathinfo($file);
        $process_check = new Process(['docker', 'run', '-v', sprintf('%s:/epub:ro', $pathinfo['dirname']), '--rm', 'epubcheck', sprintf('/epub/%s', $pathinfo['basename']), '-j', '-']);
        $process_check->mustRun();
        return $process_check;
    }

    protected static function handle_output(Process $process): array
    {
        $output = $process->getOutput();
        return json_decode(substr($output, strpos($output, '{')), true);
    }

    public static function check($file): array
    {
        $dependcheck = new dependcheck();
        try
        {
            $dependcheck->depend('docker');
            return self::handle_output(self::epubCheckDocker($file));
        } catch (DependencyFailedException $e)
        {
            $dependcheck->depend('java');
            return self::handle_output(self::epubCheckJava($file));
        }
    }
}