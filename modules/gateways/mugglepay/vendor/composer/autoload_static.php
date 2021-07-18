<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8da68385b358c84f2f29a77825ba65de
{
    public static $prefixLengthsPsr4 = array (
        'G' => 
        array (
            'Gettext\\Languages\\' => 18,
            'Gettext\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Gettext\\Languages\\' => 
        array (
            0 => __DIR__ . '/..' . '/gettext/languages/src',
        ),
        'Gettext\\' => 
        array (
            0 => __DIR__ . '/..' . '/gettext/gettext/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8da68385b358c84f2f29a77825ba65de::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8da68385b358c84f2f29a77825ba65de::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit8da68385b358c84f2f29a77825ba65de::$classMap;

        }, null, ClassLoader::class);
    }
}