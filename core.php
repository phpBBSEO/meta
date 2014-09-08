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
	public $config = array(
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
	/* Limit in chars for the last post link text. */
	public $char_limit = 25;

	// here you can comment a tag line to deactivate it
	public $tpl = array(
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

	public $meta = array(
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

	public $meta_def = array();

	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\symfony_request */
	protected $symfony_request;

	/**
	* Current $phpbb_root_path
	* @var string
	*/
	protected $phpbb_root_path;

	/**
	* Current $php_ext
	* @var string
	*/
	protected $php_ext;

	protected $filters = array('description' => 'meta_filter_txt', 'keywords' => 'make_keywords');

	/**
	* Constructor
	*
	* @param \phpbb\config\config				$config				Config object
	* @param \phpbb\template\template			$template			Template object
	* @param \phpbb\user					$user				User object
	* @param \phpbb\symfony_request				$symfony_request
	* @param string						$phpbb_root_path		Path to the phpBB root
	* @param string						$php_ext			PHP file extension
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\template\template $template, \phpbb\user $user, \phpbb\symfony_request $symfony_request, $phpbb_root_path, $php_ext)
	{
		$this->user = $user;
		$this->template = $template;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->symfony_request = $symfony_request;

		// default values, leave empty to only output the corresponding tag if filled
		$this->meta_def['robots'] = 'index,follow';

		// global values, if these are empty, the corresponding meta will not show up
		$this->meta['category'] = 'general';
		$this->meta['distribution'] = 'global';
		$this->meta['resource-type'] = 'document';

		$this->config['sitename'] = $config['sitename'];
		$this->config['site_desc'] = $config['site_desc'];
		// other settings that may be set through acp in case the mod is not used standalone
		if (isset($config['seo_meta_desc_limit']))
		{
			// defaults
			$this->meta_def['title'] = $config['seo_meta_title'];
			$this->meta_def['description'] = $config['seo_meta_desc'];
			$this->meta_def['keywords'] = $config['seo_meta_keywords'];
			$this->meta_def['robots'] = $config['seo_meta_robots'];

			// global
			$this->meta['lang'] = $config['seo_meta_lang'];
			$this->meta['copyright'] = $config['seo_meta_copy'];

			// settings
			$this->config['wordlimit'] = (int) $config['seo_meta_desc_limit'];
			$this->config['keywordlimit'] = (int) $config['seo_meta_keywords_limit'];
			$this->config['wordminlen'] = (int) $config['seo_meta_min_len'];
			$this->config['check_ignore'] = (int) $config['seo_meta_check_ignore'];
			$this->config['file_filter'] = preg_replace('`[\s]+`', '', trim($config['seo_meta_file_filter'], ', '));
			$this->config['get_filter'] = preg_replace('`[\s]+`', '', trim($config['seo_meta_get_filter'], ', '));
			$this->config['bbcodestrip'] = str_replace(',', '|', preg_replace('`[\s]+`', '', trim($config['seo_meta_bbcode_filter'], ', ')));
		}
		else
		{
			// default values, leave empty to only output the corresponding tag if filled
			$this->meta_def['title'] = $config['sitename'];
			$this->meta_def['description'] = $config['site_desc'];
			$this->meta_def['keywords'] = $config['site_desc'];

			// global values, if these are empty, the corresponding meta will not show up
			$this->meta['lang'] = $config['default_lang'];
			$this->meta['copyright'] = $config['sitename'];
		}

		$this->config['get_filter'] = !empty($this->config['get_filter']) ? @explode(',', $this->config['get_filter']) : array();
		$this->config['topic_sql'] = $config['search_type'] == 'fulltext_native' ? $this->config['topic_sql'] : false;
	}

	/**
	* add meta tag
	* $content : if empty, the called tag will show up
	* do not call to fall back to default
	*/
	public function collect($type, $content = '', $combine = false)
	{
		if ($combine)
		{
			$this->meta[$type] = (isset($this->meta[$type]) ? $this->meta[$type] . ' ' : '') . (string) $content;
		}
		else
		{
			$this->meta[$type] = (string) $content;
		}
	}

	/**
	* assign / retrun meta tag code
	*/
	public function build_meta($page_title = '', $return = false)
	{
		// If meta robots was not manually set
		if (empty($this->meta['robots']))
		{
			// Full request URI (e.g. phpBB/app.php/foo/bar)
			$request_uri = $this->symfony_request->getRequestUri();

			// Deny indexing for any url ending with htm(l) or / aznd with a qs (?)
			if (preg_match('`(\.html?|/)\?[^\?]*$`i', $request_uri))
			{
				$this->meta['robots'] = 'noindex,follow';
			}
			else
			{
				// lets still add some more specific ones
				$this->config['get_filter'] = array_merge($this->config['get_filter'], array('st','sk','sd','ch'));
			}

			// Do we allow indexing based on physical script file name
			if (empty($this->meta['robots']))
			{
				if (!empty($this->user->page['page_name']) && strpos($this->config['file_filter'], str_replace(".$this->php_ext", '', $this->user->page['page_name'])) !== false)
				{
					$this->meta['robots'] = 'noindex,follow';
				}
			}

			// Do we allow indexing based on get variable
			if (empty($this->meta['robots']))
			{
				foreach ($this->config['get_filter'] as $get)
				{
					if (isset($_GET[$get]))
					{
						$this->meta['robots'] = 'noindex,follow';
						break;
					}
				}
			}

			// fallback to default if necessary
			if (empty($this->meta['robots']))
			{
				$this->meta['robots'] = $this->meta_def['robots'];
			}
		}

		if (!empty($this->config['seo_meta_noarchive']))
		{
			$forum_id = isset($_GET['f']) ? max(0, request_var('f', 0)) : 0;

			if ($forum_id)
			{
				$forum_ids = @explode(',', preg_replace('`[\s]+`', '', trim($this->config['seo_meta_noarchive'], ', ')));

				if (in_array($forum_id, $forum_ids))
				{
					$this->meta['robots'] .= (!empty($this->meta['robots']) ? ',' : '') . 'noarchive';
				}
			}
		}

		// deal with titles, assign the tag if a default is set
		if (empty($this->meta['title']) && !empty($this->meta_def['title']))
		{
			$this->meta['title'] = $page_title;
		}

		$meta_code = '';

		foreach ($this->tpl as $key => $value)
		{
			if (isset($this->meta[$key]))
			{
				// do like this so we can deactivate one particular tag on a given page,
				// by just setting the meta to an empty string
				if (trim($this->meta[$key]))
				{
					$this->meta[$key] = isset($this->filters[$key]) ? $this->{$this->filters[$key]}($this->meta[$key]) : $this->meta[$key];
				}
			}
			else if (!empty($this->meta_def[$key]))
			{
				$this->meta[$key] = isset($this->filters[$key]) ? $this->{$this->filters[$key]}($this->meta_def[$key]) : $this->meta_def[$key];
			}

			if (trim($this->meta[$key]))
			{
				$meta_code .= sprintf($value, utf8_htmlspecialchars($this->meta[$key])) . "\n";
			}
		}

		if (!$return)
		{
			$this->template->assign_var('SEO_META_TAGS', $meta_code);
		}
		else
		{
			return $meta_code;
		}
	}

	/**
	* Returns a coma separated keyword list
	*/
	public function make_keywords($text, $decode_entities = false)
	{
		// we add ’ to the num filter because it does not seems to always be cought by punct
		// and it is widely used in languages files
		static $filter = array('`&(amp;)?[^;]+;`i', '`[[:punct:]]+`', '`[0-9’]+`',  '`[\s]+`');

		$keywords = '';
		$num = 0;
		$text = $decode_entities ? html_entity_decode(strip_tags($text), ENT_COMPAT, 'UTF-8') : strip_tags($text);
		$text = utf8_strtolower(trim(preg_replace($filter, ' ', $text)));

		if (!$text)
		{
			return '';
		}

		$text = explode(' ', trim($text));
		if ($this->config['check_ignore'])
		{
			// add stop words to $user to allow reuse
			if (empty($this->user->stop_words))
			{
				$words = array();

				if (file_exists("{$this->user->lang_path}{$this->user->lang_name}/search_ignore_words.$this->php_ext"))
				{
					// include the file containing ignore words
					include("{$this->user->lang_path}{$this->user->lang_name}/search_ignore_words.$this->php_ext");
				}

				$this->user->stop_words = & $words;
			}

			$text = array_diff($text, $this->user->stop_words);
		}

		if (empty($text))
		{
			return '';
		}

		// We take the most used words first
		$text = array_count_values($text);
		arsort($text);

		foreach ($text as $word => $count)
		{
			if ( utf8_strlen($word) > $this->config['wordminlen'] )
			{
				$keywords .= ', ' . $word;
				$num++;
				if ( $num >= $this->config['keywordlimit'] )
				{
					break;
				}
			}
		}

		return trim($keywords, ', ');
	}

	/**
	* Filter php/html tags and white spaces and string with limit in words
	*/
	public function meta_filter_txt($text, $bbcode = true)
	{
		if ($bbcode)
		{
			static $RegEx = array();
			static $replace = array();

			if (empty($RegEx))
			{
				$RegEx = array('`&(amp;)?[^;]+;`i', // HTML entitites
					'`<[^>]*>(.*<[^>]*>)?`Usi', // HTML code
				);
				$replace = array(' ', ' ');
				if (!empty($this->config['bbcodestrip']))
				{
					$RegEx[] = '`\[(' . $this->config['bbcodestrip'] . ')[^\[\]]*\].*\[/\1[^\[\]]*\]`Usi'; // bbcode to strip
					$replace[] = ' ';
				}

				$RegEx[] = '`\[\/?[a-z0-9\*\+\-]+(?:=(?:&quot;.*&quot;|[^\]]*))?(?::[a-z])?(\:[0-9a-z]{5,})\]`'; // Strip all bbcode tags
				$replace[] = '';

				$RegEx[] = '`[\s]+`'; // Multiple spaces
				$replace[] = ' ';
			}

			return $this->word_limit(preg_replace($RegEx, $replace, $text));
		}

		return $this->word_limit(preg_replace(array('`<[^>]*>(.*<[^>]*>)?`Usi', '`\[\/?[a-z0-9\*\+\-]+(?:=(?:&quot;.*&quot;|[^\]]*))?(?::[a-z])?(\:[0-9a-z]{5,})\]`', '`[\s]+`'), ' ', $text));
	}

	/**
	* Cut the text according to the number of words.
	* Borrowed from www.php.net http://www.php.net/preg_replace
	*/
	public function word_limit($string)
	{
		return count($words = preg_split('/\s+/', ltrim($string), $this->config['wordlimit'] + 1)) > $this->config['wordlimit'] ? rtrim(utf8_substr($string, 0, utf8_strlen($string) - utf8_strlen(end($words)))) . $this->config['ellipsis'] : $string;
	}
}
