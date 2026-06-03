<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'xMvxJ&qg>%z19ZTq6_,=tYwcGET19((Zv7v;L(w9X$K=g{pbt<$xtx?%jjP6PGE)' );
define( 'SECURE_AUTH_KEY',   'uus=s.w-Ic :b>2X^eW`pa3HA-8RJhqJE+($>WVx_`)R/I*VgV[uRxK]f%TjS}AW' );
define( 'LOGGED_IN_KEY',     'j[uKmdNZ)MAm=sJ!,9~!H%M[{0cF!s@rzXLpc`U,$Jd;u<ZehOIUo#u{ik4jti*K' );
define( 'NONCE_KEY',         'E;$s-n4_9(a6R}aCwje&1_$?}WfxFyLah}1^Fe{;f:>kc@+_35[iF,0iR;KZE&8f' );
define( 'AUTH_SALT',         'Mbz>?S<(cnScwRB*N@e{H$Wc/_Xa(;+aPd2`Ps+aH9;Bsr!-gTp][%F~x!WFtvly' );
define( 'SECURE_AUTH_SALT',  'Z<>GMLz;KX6RS*AAx98mcCa8*%TD)|y_w&)eh1`Gnxs3 ;yh<l~9S|~Q!)S2RocZ' );
define( 'LOGGED_IN_SALT',    'jMWfW(r3rBEkfh~o.t8cQ{:T>1Pc.^JUz1y)/m4qJDg|@OZcfyfspQjy|>1NhV4b' );
define( 'NONCE_SALT',        ';A%=WO|dv7IzX92Lai_+^UvFMQE[zngX^Hhf++~lx=<_+C@>,tUAV)eH>eUn5YvP' );
define( 'WP_CACHE_KEY_SALT', 'nCxP_?xVe]#:q9.39=`(%0FW(ifZr3D>(AYCq=X6t6<6c{759>zs0F{!&8&H}^>H' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
