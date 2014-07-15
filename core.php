<?php
/**
*
* @package Meta tag phpBB SEO
* @version $$
* @copyright (c) 2006 - 2014 www.phpbb-seo.com
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbseo\meta;

/**
* core Class
* www.phpBB-SEO.com
* @package Ultimate SEO URL phpBB SEO
*/
class core
{
	/**
	* Some config :
	*	=> keywordlimit : number of keywords (max) in the keyword tag,
	*	=> wordlimit : number of words (max) in the desc tag,
	*	=> wordminlen : only words with more than wordminlen letters will be used, default is 2,
	*	=> bbcodestrip : | separated list of bbcode to fully delete, tag + content, default is 'img|url|flash',
	*	=> ellipsis : ellipsis to use if clipping,
	*	=> topic_sql : Do a SQL to build topic meta keywords or just use the meta desc tag,
	*	=> check_ignore : Check the search_ignore_words.php list.
	*		Please note :
	*			This will require some more work for the server.
	*			And this is mostly useless if you have re-enabled the search_ignore_words.php list
	*			filtering in includes/search/fulltest_native.php (and of course use fulltest_native index).
	*	=> bypass_common : Bypass common words in viewtopic.php.
	*		Set to true by default because the most interesting keywords are as well among the most common.
	*		This of course provides with even better results when fulltest_native is used
	*		and search_ignore_words.php list was re-enabled.
	*	=> get_filter : Disallow tag based on GET var used : coma separated list, will through a disallow meta tag.
	*	=> file_filter : Disallow tag based on the physical script file name : coma separated list of file names
	* Some default values are set bellow in the seo_meta_tags() method,
	* most are acp configurable when using the Ultimate SEO URL mod :
	* => http://www.phpbb-seo.com/en/phpbb-mod-rewrite/ultimate-seo-url-t4608.html (en)
	* => http://www.phpbb-seo.com/fr/mod-rewrite-phpbb/ultimate-seo-url-t4489.html (fr)
	**/
	public static $config = array(
		'keywordlimit' => 15,
		'wordlimit' => 25,
		'wordminlen' => 2,
		'bbcodestrip' => 'img|url|flash|code',
		'ellipsis' => ' ...',
		'topic_sql' => true,
		'check_ignore' => false,
		'bypass_common' => true,
		// Consider adding ", 'p' => 1" if your forum is no indexed yet or if no post urls are to be redirected
		// to add a noindex tag on post urls
		'get_filter' => 'style,hilit,sid',
		// noindex based on physical script file name
		'file_filter' => 'ucp',
	);

	// here you can comment a tag line to deactivate it
	public static $tpl = array(
		'lang' => '<meta name="content-language" content="%s" />',
		'title' => '<meta name="title" content="%s" />',
		'description' => '<meta name="description" content="%s" />',
		'keywords' => '<meta name="keywords" content="%s" />',
		'category' => '<meta name="category" content="%s" />',
		'robots' => '<meta name="robots" content="%s" />',
		'distribution' => '<meta name="distribution" content="%s" />',
		'resource-type' => '<meta name="resource-type" content="%s" />',
		'copyright' => '<meta name="copyright" content="%s" />',
	);

	public static $meta = array(
		'title' => '',
		'description' => '',
		'keywords' => '',
		'lang' => '',
		'category' => '',
		'robots' => '',
		'distribution' => '',
		'resource-type' => '',
		'copyright' => '',
	);

	public static $meta_def = array();

	/**
	* add meta tag
	* $content : if empty, the called tag will show up
	* do not call to fall back to default
	*/
	public static function collect($type, $content = '', $combine = false)
	{
		if ($combine)
		{
			self::$meta[$type] = (isset(self::$meta[$type]) ? self::$meta[$type] . ' ' : '') . (string) $content;
		}
		else
		{
			self::$meta[$type] = (string) $content;
		}
	}
}