<?php


namespace Composer;

use Composer\Autoload\ClassLoader;
use Composer\Semver\VersionParser;
use OutOfBoundsException;
use function call_user_func_array;
use function count;


class InstalledVersions {
    private static $installed = array(
        'root'     =>
            array(
                'pretty_version' => 'dev-master',
                'version'        => 'dev-master',
                'aliases'        =>
                    array(),
                'reference'      => '6fabb62d555b03584c90820158f3c9096281ca43',
                'name'           => '__root__',
            ),
        'versions' =>
            array(
                '__root__'     =>
                    array(
                        'pretty_version' => 'dev-master',
                        'version'        => 'dev-master',
                        'aliases'        =>
                            array(),
                        'reference'      => '6fabb62d555b03584c90820158f3c9096281ca43',
                    ),
                'stil/gd-text' =>
                    array(
                        'pretty_version' => 'v1.1.0',
                        'version'        => '1.1.0.0',
                        'aliases'        =>
                            array(),
                        'reference'      => 'ae2bd5736dbd6d45a4af1e4b9c573dd4d8d06b11',
                    ),
            ),
    );
    private static $canGetVendors;
    private static $installedByVendor = array();


    public static function getInstalledPackages() {
        $packages = array();
        foreach ( self::getInstalled() as $installed ) {
            $packages[] = array_keys( $installed['versions'] );
        }


        if ( 1 === count( $packages ) ) {
            return $packages[0];
        }

        return array_keys( array_flip( call_user_func_array( 'array_merge', $packages ) ) );
    }


    public static function isInstalled( $packageName ) {
        foreach ( self::getInstalled() as $installed ) {
            if ( isset( $installed['versions'][ $packageName ] ) ) {
                return true;
            }
        }

        return false;
    }


    public static function satisfies( VersionParser $parser, $packageName, $constraint ) {
        $constraint = $parser->parseConstraints( $constraint );
        $provided   = $parser->parseConstraints( self::getVersionRanges( $packageName ) );

        return $provided->matches( $constraint );
    }


    public static function getVersionRanges( $packageName ) {
        foreach ( self::getInstalled() as $installed ) {
            if ( ! isset( $installed['versions'][ $packageName ] ) ) {
                continue;
            }

            $ranges = array();
            if ( isset( $installed['versions'][ $packageName ]['pretty_version'] ) ) {
                $ranges[] = $installed['versions'][ $packageName ]['pretty_version'];
            }
            if ( array_key_exists( 'aliases', $installed['versions'][ $packageName ] ) ) {
                $ranges = array_merge( $ranges, $installed['versions'][ $packageName ]['aliases'] );
            }
            if ( array_key_exists( 'replaced', $installed['versions'][ $packageName ] ) ) {
                $ranges = array_merge( $ranges, $installed['versions'][ $packageName ]['replaced'] );
            }
            if ( array_key_exists( 'provided', $installed['versions'][ $packageName ] ) ) {
                $ranges = array_merge( $ranges, $installed['versions'][ $packageName ]['provided'] );
            }

            return implode( ' || ', $ranges );
        }

        throw new OutOfBoundsException( 'Package "' . $packageName . '" is not installed' );
    }


    public static function getVersion( $packageName ) {
        foreach ( self::getInstalled() as $installed ) {
            if ( ! isset( $installed['versions'][ $packageName ] ) ) {
                continue;
            }

            if ( ! isset( $installed['versions'][ $packageName ]['version'] ) ) {
                return null;
            }

            return $installed['versions'][ $packageName ]['version'];
        }

        throw new OutOfBoundsException( 'Package "' . $packageName . '" is not installed' );
    }


    public static function getPrettyVersion( $packageName ) {
        foreach ( self::getInstalled() as $installed ) {
            if ( ! isset( $installed['versions'][ $packageName ] ) ) {
                continue;
            }

            if ( ! isset( $installed['versions'][ $packageName ]['pretty_version'] ) ) {
                return null;
            }

            return $installed['versions'][ $packageName ]['pretty_version'];
        }

        throw new OutOfBoundsException( 'Package "' . $packageName . '" is not installed' );
    }


    public static function getReference( $packageName ) {
        foreach ( self::getInstalled() as $installed ) {
            if ( ! isset( $installed['versions'][ $packageName ] ) ) {
                continue;
            }

            if ( ! isset( $installed['versions'][ $packageName ]['reference'] ) ) {
                return null;
            }

            return $installed['versions'][ $packageName ]['reference'];
        }

        throw new OutOfBoundsException( 'Package "' . $packageName . '" is not installed' );
    }


    public static function getRootPackage() {
        $installed = self::getInstalled();

        return $installed[0]['root'];
    }


    public static function getRawData() {
        return self::$installed;
    }


    public static function reload( $data ) {
        self::$installed         = $data;
        self::$installedByVendor = array();
    }


    private static function getInstalled() {
        if ( null === self::$canGetVendors ) {
            self::$canGetVendors = method_exists( 'Composer\Autoload\ClassLoader', 'getRegisteredLoaders' );
        }

        $installed = array();

        if ( self::$canGetVendors ) {

            foreach ( ClassLoader::getRegisteredLoaders() as $vendorDir => $loader ) {
                if ( isset( self::$installedByVendor[ $vendorDir ] ) ) {
                    $installed[] = self::$installedByVendor[ $vendorDir ];
                } elseif ( is_file( $vendorDir . '/composer/installed.php' ) ) {
                    $installed[] = self::$installedByVendor[ $vendorDir ] = require $vendorDir . '/composer/installed.php';
                }
            }
        }

        $installed[] = self::$installed;

        return $installed;
    }
}
