<?php
/*
Plugin Name: CDN
Description: Change all static files urls to CDN domain
Version: 2.0
Plugin URI: https://github.com/shtrihstr/wp-cdn
Author: Oleksandr Strikha
Author URI: https://github.com/shtrihstr
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if( defined( 'CDN_DOMAIN' ) ) {

    // legacy for prev version
    if( ! function_exists( 'get_cloudfront_attachment_url' ) ) {
        function get_cloudfront_attachment_url ($uri, $in_text = false ) {
            return get_cdn_attachment_url( $uri );
        }
    }

    if( ! function_exists( 'get_cdn_attachment_url' ) ) {
        function get_cdn_attachment_url( $uri ) {

            if( ! apply_filters( 'cdn_url_replace_enabled', true ) ) {
                return $uri;
            }

            if( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) && is_admin()  ) {
                return $uri; // avoid CDN url in editor
            }

            // get site hosts
            if ( false === ( $hosts = wp_cache_get( 'site-hosts', 'cdn' ) ) ) {

                $hosts = [ parse_url( home_url(), PHP_URL_HOST )] ;

                if ( defined( 'DOMAIN_MAPPING' ) ) {

                    global $wpdb, $blog_id;
                    $domains1 = $wpdb->get_col( "SELECT domain FROM {$wpdb->blogs} WHERE blog_id = '$blog_id'" );

                    $mapping = $wpdb->base_prefix . 'domain_mapping';
                    $domains2 = $wpdb->get_col( "SELECT domain FROM {$mapping} WHERE blog_id = '$blog_id'" );
                    $hosts = array_merge( $hosts, $domains1, $domains2 );
                }

                $hosts = array_unique( $hosts );
                wp_cache_set( 'cf-site-hosts', $hosts, 'cdn', HOUR_IN_SECONDS );
            }

            $regex_hosts = implode( '|', array_map( 'preg_quote', $hosts ) );

            $ext = [
                // images
                'png', 'jpg', 'jpeg', 'gif', 'tif', 'bmp',
                // assets
                'css', 'js',
                // archives
                'zip', 'gz', 'tar',
                // fonts
                'ttf', 'eot', 'svg', 'woff', 'woff2',
                // video
                'webm', 'mp4', 'm4v',
                //audio
                'mp3', 'wav',
            ];

            $ext = apply_filters( 'cdn_extensions', $ext );

            $regex_ext = implode( '|', array_map( 'preg_quote', $ext ) );

            $regex = "https?:\/\/($regex_hosts)\/(([^\"^']+)\.($regex_ext))";

            $cf_url = 'https://' . CDN_DOMAIN;

            return preg_replace( "/$regex/Ui", "$cf_url/$2", $uri );
        }
    }

    $filters = [
        'script_loader_src',
        'style_loader_src',
        'wp_get_attachment_url',
        'the_content',
    ];

    foreach ( $filters as $filter ) {
        add_filter( $filter, 'get_cdn_attachment_url', 999 );
    }

    add_filter( 'wp_calculate_image_srcset', function( $sources ) {
        foreach ( $sources as $key => $source ) {
            $sources[ $key ]['url'] = get_cdn_attachment_url( $source['url'] );
        }
        return $sources;
    } );

    $add_version_to_url = function( $url ) {
        return add_query_arg( 'ver', get_option( 'cdn-assets-version', '1.0' ), $url );
    };

    add_filter( 'script_loader_src', $add_version_to_url );
    add_filter( 'style_loader_src', $add_version_to_url );

    add_filter( 'editor_stylesheets', function( $stylesheets ) use ( $add_version_to_url ) {
        return array_map( $add_version_to_url, $stylesheets );
    } );

    add_action( 'muplugins_loaded', function() {
        if( function_exists( 'flush_cache_add_button' ) ) {
            flush_cache_add_button( __( 'Assets cache' ), function() {
                update_option( 'cdn-assets-ver',  sprintf( '%0.1f', get_option( 'cdn-assets-ver', '1.0' ) + 0.1 ) );
            } );
        }
    } );

}


