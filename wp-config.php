<?php
define( 'WP_CACHE', true );


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
define( 'DB_NAME', 'u335600898_SjhOl' );

/** Database username */
define( 'DB_USER', 'u335600898_Xig2J' );

/** Database password */
define( 'DB_PASSWORD', 'aqkTimYb4Q' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

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
define( 'AUTH_KEY',          'y-x)f66!D{v5$Q7327ZIHOCz!NeouVX5>@R(:HE@HeZB2{(=844DleP$ExCw%RTK' );
define( 'SECURE_AUTH_KEY',   '30U/~$el.ng9]MhOSi}I-,|yxYW~c%sb]f>V&Tfb^D3G`shknYk#2Nc4iFFirHgF' );
define( 'LOGGED_IN_KEY',     '[ :o0=u$[#-.*pOcO;e0A||5U)K.2,ag9b?;Zr$>NhUQ0gO6v9P2]r6_*PEP>,r#' );
define( 'NONCE_KEY',         '^bl-*A`g;DMhaGnLX0eg=y[XU}$>A^qeWp5o(SRyh8kdQ$Idy0+p^hoz9_x^fi$q' );
define( 'AUTH_SALT',         'oSb_tK:3J]A>$ANVM~:iR-7ZKi1v!s`i[P(r:C)`xEI$*nX?5*HvW y3F$pCY?B;' );
define( 'SECURE_AUTH_SALT',  'z9)p;PA_z <a2q0K0P@v%&gj/om?r21D*uDb$P2mDZ2G*i-Lh>H~:NdvHS%}uoIp' );
define( 'LOGGED_IN_SALT',    'lU9FfORaBmt]V(JX+AKli bIY2Y2p&{FvxGNeGtSgxD&9>,Al~<e=U/dSV @n,YQ' );
define( 'NONCE_SALT',        'd9-*/L%+>WZ=2bXw;=?4dD!;,O$A#:.[^/ikuv-ouPZ>ys6_d0TUiuFLj^pS!_ N' );
define( 'WP_CACHE_KEY_SALT', '!Avd6jer[P}:l+F<DnCW$_^3N3Tw@9?Dqe`^lUp+=*%iQw5j+gd-W.$bZ+$Pa(dN' );


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

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', '56807c7217a1ca3dd46a72430e1edbe1' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
