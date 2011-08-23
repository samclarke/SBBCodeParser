<?php
/**
 * SSBBCodeParser
 * 
 * BBCode parser classes.
 *
 * @copyright (C) 2011 Sam Clarke (samclarke.com)
 * @license http://www.gnu.org/licenses/lgpl.html LGPL version 3 or higher
 *
 * @TODO: Add inline/block to tags and forbid adding block elements to inline ones.
 * Maybe split the inline elemnt and put the block element inbetween?
 * @TODO: Have options for limiting nesting of tags
 * @TODO: Have whitespace trimming options for tags
 */

/*
 * This library is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

class SBBCodeParser_Exception extends Exception
{
}

class SBBCodeParser_MissingEndTagException extends SBBCodeParser_Exception
{
}

class SBBCodeParser_InvalidNestingException extends SBBCodeParser_Exception
{
}


abstract class SBBCodeParser_Node
{
	/**
	 * Nodes parent
	 * @var SBBCodeParser_ContainerNode
	 */
	protected $parent;
	
	/**
	 * Nodes root parent
	 * @var SBBCodeParser_ContainerNode
	 */
	protected $root;


	/**
	 * Sets the nodes parent
	 * @param SBBCodeParser_Node $parent
	 */
	public function set_parent(SBBCodeParser_ContainerNode $parent=null)
	{
		$this->parent = $parent;
		
		if($parent instanceof SBBCodeParser_Document)
			$this->root = $parent;
		else
			$this->root = $parent->root();
	}

	/**
	 * Gets the nodes parent. Returns null if there
	 * is no parent
	 * @return SBBCodeParser_Node
	 */
	public function parent()
	{
		return $this->parent;
	}
	
	/**
	 * @return string
	 */
	public function get_html()
	{
		return null;
	}
	
	/**
	 * Gets the nodes root node
	 * @return SBBCodeParser_Node
	 */
	public function root()
	{
		return $this->root;
	}
	
	/**
	 * Finds a parent node of the passed type.
	 * Returns null if none found.
	 * @param string $tag
	 * @return SBBCodeParser_TagNode 
	 */
	public function find_parent_by_tag($tag)
	{
		$node = $this->parent();
		
		while($this->parent() != null
			&& !$node instanceof SBBCodeParser_Document)
		{
			if($node->tag() === $tag)
				return $node;
			
			$node = $node->parent();
		}
		
		return null;
	}
}

abstract class SBBCodeParser_ContainerNode extends SBBCodeParser_Node
{
	/**
	 * Array of child nodes
	 * @var array
	 */
	protected $children = array();


	/**
	 * Adds a SBBCodeParser_Node as a child
	 * of this node.
	 * @param $child The child node to add
	 * @return void
	 */
	public function add_child(SBBCodeParser_Node $child)
	{
		$this->children[] = $child;
		$child->set_parent($this);
	}

	/**
	 * Replaces a child node
	 * @param SBBCodeParser_Node $what
	 * @param mixed $with SBBCodeParser_Node or an array of SBBCodeParser_Node
	 * @return bool 
	 */
	public function replace_child(SBBCodeParser_Node $what, $with)
	{
		$replace_key = array_search($what, $this->children);

		if($replace_key === false)
			return false;

		if(is_array($with))
			foreach($with as $child)
				$child->set_parent($this);

		array_splice($this->children, $replace_key, 1, $with);

		return true;
	}

	/**
	 * Removes a child fromthe node
	 * @param SBBCodeParser_Node $child
	 * @return bool
	 */
	public function remove_child(SBBCodeParser_Node $child)
	{
		$key = array_search($what, $this->children);

		if($key === false)
			return false;

		$this->children[$key]->set_parent();
		unset($this->children[$key]);
		return true;
	}

	/**
	 * Gets the nodes children
	 * @return array
	 */
	public function children()
	{
		return $this->children;
	}

	/**
	 * Gets the last child of type SBBCodeParser_TagNode.
	 * @return SBBCodeParser_TagNode
	 */
	public function last_tag_node()
	{
		$children_len = count($this->children);

		for($i=$children_len-1; $i >= 0; $i--)
			if($this->children[$i] instanceof SBBCodeParser_TagNode)
				return $this->children[$i];

		return null;
	}
	
	/**
	 * Gets a HTML representation of this node 
	 * @return string
	 */
	public function get_html($nl2br=true)
	{
		$html = '';
		
		foreach($this->children as $child)
			$html .= $child->get_html($nl2br);
		
		if($this instanceof SBBCodeParser_Document)
			return $html;
		
		$bbcode = $this->root()->get_bbcode($this->tag);
			
		if(is_callable($bbcode->handler()) && ($func = $bbcode->handler()) !== false)
			return $func($html, $this->attribs, $this);
			//return call_user_func($bbcode->handler(), $html, $this->attribs, $this);

		return str_replace('%content%', $html, $bbcode->handler());
	}
	
	/**
	 * Gets the raw text content of this node
	 * and it's children.
	 * 
	 * The returned text is UNSAFE and should not
	 * be used without filtering!
	 * @return string
	 */
	public function get_text()
	{
		$text = '';
		
		foreach($this->children as $child)
			$text .= $child->get_text();
		
		return $text;
	}
}

class SBBCodeParser_TextNode extends SBBCodeParser_Node
{
	protected $text;
	
	
	public function __construct($text)
	{
		$this->text = $text;
	}
	
	public function get_html($nl2br=true)
	{
		if(!$nl2br)
			return str_replace("  ", " &nbsp;", htmlentities($this->text, ENT_QUOTES | ENT_IGNORE, "UTF-8"));
		
		return str_replace("  ", " &nbsp;", nl2br(htmlentities($this->text, ENT_QUOTES | ENT_IGNORE, "UTF-8")));
	}
	
	public function get_text()
	{
		return $this->text;
	}
}

class SBBCodeParser_TagNode extends SBBCodeParser_ContainerNode
{
	/**
	 * Tag name of this node
	 * @var string
	 */
	protected $tag;
	
	/**
	 * Assoc array of attributes
	 * @var array
	 */
	protected $attribs;
	
	
	public function __construct($tag, $attribs)
	{
		$this->tag     = $tag;
		$this->attribs = $attribs;
	}
	
	/**
	 * Gets the tag of this node
	 * @return string
	 */
	public function tag()
	{
		return $this->tag;
	}
	
	/**
	 * Gets the tags attributes
	 * @return array
	 */
	public function attributes()
	{
		return $this->attribs;
	}
}


class SBBCodeParser_BBCode
{
	/**
	 * The tag this BBCode applies to
	 * @var string
	 */
	protected $tag;
		
	/**
	 * The BBCodes handler
	 * @var mixed string or function
	 */
	protected $handler;
	
	/**
	 * If the tag is a self closing tag
	 * @var bool
	 */
	protected $is_self_closing;
	
	/**
	 * Array of tags which will cause this tag to close
	 * if they are encountered before the end of it.
	 * Used for [*] which may not have a closing tag so
	 * other [*] or [/list] tags will cause it to be closed·
	 * @var array
	 */
	protected $closing_tags;
	
	/**
	 * Valid child nodes for this tag. Tags like list, table,
	 * ect. will only accept li, tr, ect. tags and not text nodes
	 * @var array
	 */
	protected $accepted_children;

	/**
	 * Which auto detections this BBCode should be excluded from
	 * @var int
	 */
	protected $is_inline;

	const AUTO_DETECT_EXCLUDE_NONE		= 0;
	const AUTO_DETECT_EXCLUDE_URL		= 2;
	const AUTO_DETECT_EXCLUDE_EMAIL		= 4;
	const AUTO_DETECT_EXCLUDE_EMOTICON	= 8;
	const AUTO_DETECT_EXCLUDE_ALL		= 15;

	const BLOCK_TAG		= false;
	const INLINE_TAG	= true;


	/**
	 * Creates a new BBCode
	 * @param string $tag                 Tag this BBCode is for
	 * @param mixed  $handler             String or function, should return a string
	 * @param bool   $is_inline           If this tag is an inline tag or a block tag
	 * @param array  $is_self_closing     If this tag is self closing, I.E. doesn't need [/tag]
	 * @param array  $closing_tags        Tags which will close this tag
	 * @param int    $accepted_children   Tags allowed as children of this BBCode. Can also include text_node
	 * @param int    $auto_detect_exclude Which auto detections to exclude this BBCode from
	 */
	public function __construct($tag,
		$handler,
		$is_inline=SBBCodeParser_BBCode::INLINE_TAG,
		$is_self_closing=false,
		$closing_tags=array(),
		$accepted_children=array(),
		$auto_detect_exclude=SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_NONE)
	{
		$this->tag                 = $tag;
		$this->is_inline           = $is_inline;
		$this->handler             = $handler;
		$this->is_self_closing     = $is_self_closing;
		$this->closing_tags        = $closing_tags;
		$this->accepted_children   = $accepted_children;
		$this->auto_detect_exclude = $auto_detect_exclude;
	}
	
	/**
	 * Gets the tag name this BBCode is for
	 * @return string
	 */
	public function tag()
	{
		return $this->tag;
	}
	
	/**
	 * Gets if this BBCode is inline or if it's block
	 * @return bool
	 */
	public function is_inline()
	{
		return $this->is_inline;
	}

	/**
	 * Gets if this BBCode is self closing
	 * @return bool
	 */
	public function is_self_closing()
	{
		return $this->is_self_closing;
	}
	
	/**
	 * Gets the format string/handler for this BBCode
	 * @return mixed String or function
	 */
	public function handler()
	{
		return $this->handler;
	}
	
	/**
	 * Gets an array of tags which will cause this tag to be closed
	 * @return array
	 */
	public function closing_tags()
	{
		return $this->closing_tags;
	}
	
	/**
	 * Gets an array of tags which are allowed as children of this tag
	 * @return array
	 */
	public function accepted_children()
	{
		return $this->accepted_children;
	}

	/**
	 *  Which auto detections this BBCode should be excluded from
	 * @return int
	 */
	public function auto_detect_exclude()
	{
		return $this->auto_detect_exclude;
	}
}

class SBBCodeParser_Document extends SBBCodeParser_ContainerNode
{
	/**
	 * Current Tag
	 * @var SBBCodeParser_ContainerNode
	 */
	protected $current_tag = null;

	/**
	 * Assoc array of all the BBCodes for this document
	 * @var array
	 */
	protected $bbcodes = array();

	/**
	 * Base URI to be used for links, images, ect.
	 * @var string
	 */
	protected $base_uri = null;

	/**
	 * Assoc array of emoticons in smiley_code => smiley_url format
	 * @var array
	 */
	protected $emoticons = array();

	/*
	 * If to throw errors when encountering bad BBCode or
	 * to silently try and fix
	 * @var bool
	 */
	protected $throw_errors = false;
	
	
	public function __construct($load_defaults=true, $throw_errors=true)
	{
		$this->throw_errors = $throw_errors;

		// Load default BBCodes
		if($load_defaults)
			$this->add_bbcodes($this->default_bbcodes());
	}

	/**
	 * Gets an array of the default BBCodes
	 * @return array
	 */
	public static function default_bbcodes()
	{
		return array(
		    	new SBBCodeParser_BBCode('b', '<strong>%content%</strong>'),
			new SBBCodeParser_BBCode('i', '<em>%content%</em>'),
			new SBBCodeParser_BBCode('strong', '<strong>%content%</strong>'),
			new SBBCodeParser_BBCode('em', '<em>%content%</em>'),
			new SBBCodeParser_BBCode('u', '<span style="text-decoration: underline">%content%</span>'),
			new SBBCodeParser_BBCode('s', '<span style="text-decoration: line-through">%content%</span>'),
			new SBBCodeParser_BBCode('blink', '<span style="text-decoration: blink">%content%</span>'),
			new SBBCodeParser_BBCode('sub', '<sub>%content%</sub>'),
			new SBBCodeParser_BBCode('sup', '<sup>%content%</sup>'),
			new SBBCodeParser_BBCode('ins', '<ins>%content%</ins>'),
			new SBBCodeParser_BBCode('del', '<del>%content%</del>'),

			new SBBCodeParser_BBCode('right', '<div style="text-align: right">%content%</div>', SBBCodeParser_BBCode::BLOCK_TAG),
			new SBBCodeParser_BBCode('left', '<div style="text-align: left">%content%</div>', SBBCodeParser_BBCode::BLOCK_TAG),
			new SBBCodeParser_BBCode('center', '<div style="text-align: center">%content%</div>', SBBCodeParser_BBCode::BLOCK_TAG),
			new SBBCodeParser_BBCode('justify', '<div style="text-align: justify">%content%</div>', SBBCodeParser_BBCode::BLOCK_TAG),

			// notes only show in editing so ignore it
			new SBBCodeParser_BBCode('note', ''),
			new SBBCodeParser_BBCode('hidden', ''),

			new SBBCodeParser_BBCode('abbr', function($content, $attribs)
			{
				return '<abbr title="' . $attribs['default'] . '">' . $content . '</abbr>';
			}),
			new SBBCodeParser_BBCode('acronym', function($content, $attribs)
			{
				return '<acronym title="' . $attribs['default'] . '">' . $content . '</acronym>';
			}),

		    	new SBBCodeParser_BBCode('icq', '<a href="http://www.icq.com/people/about_me.php?uin=%content%">
				<img  src="http://status.icq.com/online.gif?icq=%content%&amp;img=5"> %content%</a>',
				SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
		    	new SBBCodeParser_BBCode('skype', '<a href="skype:jovisa737590?call">
				<img src="http://mystatus.skype.com/bigclassic/%content%" style="border: none;" width="182"
				height="44" alt="My status" /></a>',
				SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),

		    	new SBBCodeParser_BBCode('bing', '<a href="http://www.bing.com/search?q=%content%">%content%</a>',
				SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
		    	new SBBCodeParser_BBCode('google', '<a href="http://www.google.com/search?q=%content%">%content%</a>',
				SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
			new SBBCodeParser_BBCode('wikipedia', '<a href="http://www.wikipedia.org/wiki/%content%">%content%</a>',
				SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
			new SBBCodeParser_BBCode('youtube', function($content, $attribs)
			{
				if(substr($content, 0, 23) === 'http://www.youtube.com/')
					$uri = $content;
				else
					$uri = 'http://www.youtube.com/v/' . $content;

				return '<iframe width="480" height="390" src="' . $uri . '" frameborder="0"></iframe>';
			}, SBBCodeParser_BBCode::BLOCK_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
			new SBBCodeParser_BBCode('vimeo', function($content, $attribs)
			{
				if(substr($content, 0, 24) === 'http://player.vimeo.com/')
					$uri = $content;
				else if(substr($content, 0, 17) === 'http://vimeo.com/'
					|| substr($content, 0, 21) === 'http://www.vimeo.com/'
					&& preg_match("/http:\/\/(?:www\.)?vimeo\.com\/([0-9]{4,10})/", $content, $matches))
				{
					preg_match("/http:\/\/(?:www\.)?vimeo\.com\/([0-9]{4,10})/", $content, $matches);
					$uri = 'http://player.vimeo.com/video/' . $matches[1] . '?title=0&amp;byline=0&amp;portrait=0';
				}
				else
					$uri = 'http://player.vimeo.com/video/' . $content . '?title=0&amp;byline=0&amp;portrait=0';

				return '<iframe src="' . $uri . '" width="400" height="225" frameborder="0"></iframe>';
			}, SBBCodeParser_BBCode::BLOCK_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
			new SBBCodeParser_BBCode('flash', function($content, $attribs)
			{
				$width = 640;
				$height = 385;
			
				if(substr($content, 0, 4) !== 'http')
					$content = $node->root()->get_base_uri() . $content;
			
				if(isset($attribs['width']) && is_numeric($attribs['width']))
					$width = $attribs['width'];
				if(isset($attribs['height']) && is_numeric($attribs['height']))
					$height = $attribs['height'];
			
				// for [flash=200,100] format
				if(!empty($attribs['default']))
				{
					list($w, $h) = explode(',', $attribs['default']);
				
					if($w > 20 && is_numeric($w))
						$width = $w;
				
					if($h > 20 && is_numeric($h))
						$height = $h;
				}

				return '<object width="' . $width. '" height="' . $height. '">
						<param name="movie" value="' . $content . '"></param>
						<embed src="' . $content . '"
							type="application/x-shockwave-flash"
							width="' . $width. '" height="' . $height. '">
						</embed>
					</object>';
			}, SBBCodeParser_BBCode::BLOCK_TAG),

			new SBBCodeParser_BBCode('paypal', function($content, $attribs)
			{
				$content = urlencode($content);

				return '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business='
					. $content . '&lc=US&no_note=0&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHostedGuest">
				<img src="https://www.paypal.com/en_US/i/btn/x-click-but21.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!"/></a>';
			},
			SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),


			new SBBCodeParser_BBCode('pastebin', function($content, $attribs)
			{
				if(!preg_match("/^[a-zA-Z0-9]$/", $content))
				{
					preg_match("#http://pastebin.com/([a-zA-Z0-9]+)#", $content, $matches);
					$content = '';

					if(isset($matches[1]))
						$content = $matches[1];
				}

				return '<script src="http://pastebin.com/embed_js.php?i=' . $content . '"></script>';
			}, SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
			new SBBCodeParser_BBCode('gist', function($content, $attribs)
			{
				if($content != (string)intval($content))
				{
					preg_match("#https://gist.github.com/([0-9]+)#", $content, $matches);
					$content = '';

					if(isset($matches[1]))
						$content = $matches[1];
				}
				else
					$content = intval($content);

				return '<script src="http://gist.github.com/' . $content . '.js"></script>';
			}, SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),

			new SBBCodeParser_BBCode('twitter', '<a href="https://twitter.com/%content%"
				class="twitter-follow-button" data-show-count="false">Follow @%content%</a>
				<script src="http://platform.twitter.com/widgets.js" type="text/javascript"></script>',
				SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
			new SBBCodeParser_BBCode('tweets', "<script src=\"http://widgets.twimg.com/j/2/widget.js\"></script>
				<script>
				new TWTR.Widget({
					version: 2,
					type: 'profile',
					rpp: 3,
					interval: 6000,
					width: 400,
					height: 150,
					theme: {
						shell: {
							background: '#333333',
							color: '#ffffff'
						},
						tweets: {
							background: '#000000',
							color: '#ffffff',
							links: '#4aed05'
						}
					},
					features: {
						scrollbar: false,
						loop: false,
						live: false,
						hashtags: true,
						timestamp: true,
						avatars: false,
						behavior: 'all'
					}
				}).render().setUser('%content%').start();
				</script>",
				SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),

			new SBBCodeParser_BBCode('googlemaps', '<iframe src="http://maps.google.com/maps?q=%content%&amp;output=embed"
				scrolling="no" width="100%" height="350" frameborder="0"></iframe>',
				SBBCodeParser_BBCode::BLOCK_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),

			new SBBCodeParser_BBCode('pdf', '<iframe src="http://docs.google.com/gview?url=%content%&amp;embedded=true"
				width="100%" height="500" frameborder="0"></iframe>',
				SBBCodeParser_BBCode::BLOCK_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),

			new SBBCodeParser_BBCode('scribd', function($content, $attribs)
			{
				if(!isset($attribs['id'])
					|| !($attribs['id'] = intval($attribs['id'])) > 1)
					return 'Invalid scribd ID.';

				if(!isset($attribs['key']))
					return 'Missing scribd key.';

				return '<iframe src="http://www.scribd.com/embeds/' . $attribs['id'] . '/content?start_page=1&view_mode=list&access_key=' . $attribs['key'] . '"
					data-auto-height="true" data-aspect-ratio="1" scrolling="no" width="100%"
					height="500" frameborder="0"></iframe>
					<script type="text/javascript">(function() {
						var scribd = document.createElement("script");
						scribd.type = "text/javascript";
						scribd.async = true;
						scribd.src = "http://www.scribd.com/javascripts/embed_code/inject.js";
						var s = document.getElementsByTagName("script")[0];
						s.parentNode.insertBefore(scribd, s);
					})();</script>';
			}, SBBCodeParser_BBCode::BLOCK_TAG, true, array(), array(), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),

			new SBBCodeParser_BBCode('spoiler', '<div class="spoiler" style="margin:5px 15px 15px 15px">
				<div style="margin:0 0 2px 0; font-weight:bold;">Spoiler:
					<input type="button" value="Show"
						onclick="if (this.value == \'Show\')
							{
								this.parentNode.parentNode.getElementsByTagName(\'div\')[1].getElementsByTagName(\'div\')[0].style.display = \'block\';
								this.value = \'Hide\';
							} else {
								this.parentNode.parentNode.getElementsByTagName(\'div\')[1].getElementsByTagName(\'div\')[0].style.display = \'none\';
								this.value = \'Show\';
							}" />
				</div>
				<div style="margin:0; padding:6px; border:1px inset;">
					<div style="display: none;">
						%content%
					</div>
				</div>
			</div>'),

		    	new SBBCodeParser_BBCode('tt', '<span style="font-family: monospace">%content%</span>'),

		    	new SBBCodeParser_BBCode('pre', function($content, $attribs, $node)
			{
				$content = '';
				foreach($node->children() as $child)
					$content .= $child->get_html(false);

				return "<pre>{$content}</pre>";
			}, SBBCodeParser_BBCode::BLOCK_TAG),
		    	new SBBCodeParser_BBCode('code', '<code>%content%</code>',
				SBBCodeParser_BBCode::BLOCK_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_EMOTICON),
		    	new SBBCodeParser_BBCode('php', function($content, $attribs, $node)
			{
				ob_start();
				highlight_string($node->get_text());
				$content = ob_get_contents();
				ob_end_clean();

				return "<code class=\"php\">{$content}</code>";
			}, SBBCodeParser_BBCode::BLOCK_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_EMOTICON),
		
			new SBBCodeParser_BBCode('quote', function($content, $attribs, $node)
			{
				$cite = '';
				if(!empty($attribs['default']))
					$cite = "<cite>{$attribs['default']}:</cite>";

				if($node->find_parent_by_tag('quote') !== null)
					return "</p><blockquote><p>{$cite}{$content}</p></blockquote><p>";
			
				return "<blockquote><p>{$cite}{$content}</p></blockquote>";
			}, SBBCodeParser_BBCode::BLOCK_TAG),
		    
			new SBBCodeParser_BBCode('font', function($content, $attribs)
			{
				// Font can have letters, spaces, quotes, - and commas
				if(!isset($attribs['default'])
					|| !preg_match("/^([A-Za-z\'\",\- ]+)$/", $attribs['default']))
					$attribs['default'] = 'Arial';

				return '<span style="font-family: ' . $attribs['default'] . '">' . $content . '</span>';
			}),
			new SBBCodeParser_BBCode('size', function($content, $attribs)
			{
				$size = 'xx-small';

				/*
				Font tag sizes 1-7 should be:
				1 = xx-small
				2 = small
				3 = medium
				4 = large
				5 = x-large
				6 = xx-large
				7 = ?? in chrome it's 48px
				*/
				if(!isset($attribs['default']))
					$size = 'xx-small';
				else if($attribs['default'] == 2)
					$size = 'small';
				else if($attribs['default'] == 3)
					$size = 'medium';
				else if($attribs['default'] == 4)
					$size = 'large';
				else if($attribs['default'] == 5)
					$size = 'x-large';
				else if($attribs['default'] == 6)
					$size = 'xx-large';
				else if($attribs['default'] == 7)
					$size = '48px';
				else if($attribs['default'][strlen($attribs['default']) - 1] === '%'
					&& is_numeric(substr($attribs['default'], 0, -1)))
					$size = $attribs['default'];
				else
				{
					if(!is_numeric($attribs['default']))
						$attribs['default'] = 13;
					if($attribs['default'] < 6)
						$attribs['default'] = 6;
					if($attribs['default'] > 48)
						$attribs['default'] = 48;
		
					$size = $attribs['default'] . 'px';
				}

				return '<span style="font-size: ' . $size . '">' . $content . '</span>';
			}),
			new SBBCodeParser_BBCode('color', function($content, $attribs)
			{
				// colour must be either a hex #xxx/#xxxxxx or a word with no spaces red/blue/ect.
				if(!isset($attribs['default'])
					|| !preg_match("/^(#[a-fA-F0-9]{3,6}|[A-Za-z]+)$/", $attribs['default']))
					$attribs['default'] = '#000';

				return '<span style="color: ' . $attribs['default'] . '">' . $content . '</span>';
			}),

			new SBBCodeParser_BBCode('list', function($content, $attribs)
			{
				$style = 'circle';
				$type  = 'ul';

				switch($attribs['default'])
				{
					case 'd':
						$style = 'disc';
						$type  = 'ul';
						break;
					case 's':
						$style = 'square';
						$type  = 'ul';
						break;
					case '1':
						$style = 'decimal';
						$type  = 'ol';
						break;
					case 'a':
						$style = 'lower-alpha';
						$type  = 'ol';
						break;
					case 'A':
						$style = 'upper-alpha';
						$type  = 'ol';
						break;
					case 'i':
						$style = 'lower-roman';
						$type  = 'ol';
						break;
					case 'I':
						$style = 'upper-roman';
						$type  = 'ol';
						break;
				}

				return "<{$type} style=\"list-style: {$style}\">{$content}</{$type}>";
			}, SBBCodeParser_BBCode::BLOCK_TAG, false, array(), array('*', 'li', 'ul', 'li', 'ol', 'list')),
			new SBBCodeParser_BBCode('ul', '<ul>%content%</ul>', SBBCodeParser_BBCode::BLOCK_TAG),
			new SBBCodeParser_BBCode('ol', '<ol>%content%</ol>', SBBCodeParser_BBCode::BLOCK_TAG),
			new SBBCodeParser_BBCode('li', '<li>%content%</li>'),
			new SBBCodeParser_BBCode('*', '<li>%content%</li>', SBBCodeParser_BBCode::BLOCK_TAG, false,
				array('*', 'li', 'ul', 'li', 'ol', '/list')),

			new SBBCodeParser_BBCode('table', '<table>%content%</table>', SBBCodeParser_BBCode::BLOCK_TAG,
				false, array(), array('table', 'th', 'h', 'tr', 'row', 'r', 'td', 'col', 'c')),
			new SBBCodeParser_BBCode('th', '<th>%content%</th>'),
			new SBBCodeParser_BBCode('h', '<th>%content%</th>'),
			new SBBCodeParser_BBCode('tr', '<tr>%content%</tr>', SBBCodeParser_BBCode::BLOCK_TAG, false,
				array(), array('table', 'th', 'h', 'tr', 'row', 'r', 'td', 'col', 'c')),
			new SBBCodeParser_BBCode('row', '<tr>%content%</tr>', SBBCodeParser_BBCode::BLOCK_TAG, false,
				array(), array('table', 'th', 'h', 'tr', 'row', 'r', 'td', 'col', 'c')),
			new SBBCodeParser_BBCode('r', '<tr>%content%</tr>', SBBCodeParser_BBCode::BLOCK_TAG, false,
				array(), array('table', 'th', 'h', 'tr', 'row', 'r', 'td', 'col', 'c')),
			new SBBCodeParser_BBCode('td', '<td>%content%</td>'),
			new SBBCodeParser_BBCode('col', '<td>%content%</td>'),
			new SBBCodeParser_BBCode('c', '<td>%content%</td>'),
	
			new SBBCodeParser_BBCode('notag', '%content%', SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'),
				SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
			new SBBCodeParser_BBCode('nobbc', '%content%', SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'),
				SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
			new SBBCodeParser_BBCode('noparse', '%content%', SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'),
				SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
	
			new SBBCodeParser_BBCode('h1', '<h1>%content%</h1>'),
			new SBBCodeParser_BBCode('h2', '<h2>%content%</h2>'),
			new SBBCodeParser_BBCode('h3', '<h3>%content%</h3>'),
			new SBBCodeParser_BBCode('h4', '<h4>%content%</h4>'),
			new SBBCodeParser_BBCode('h5', '<h5>%content%</h5>'),
			new SBBCodeParser_BBCode('h6', '<h6>%content%</h6>'),
			new SBBCodeParser_BBCode('h7', '<h7>%content%</h7>'),

			new SBBCodeParser_BBCode('big', '<span style="font-size: large">%content%</span>'),
			new SBBCodeParser_BBCode('small', '<span style="font-size: x-small">%content%</span>'),
			// tables use this tag so can't be a header.
			//new SBBCodeParser_BBCode('h', '<h5>%content%</h5>'),

			new SBBCodeParser_BBCode('br', '<br />', SBBCodeParser_BBCode::INLINE_TAG, true),
			new SBBCodeParser_BBCode('sp', '&nbsp;', SBBCodeParser_BBCode::INLINE_TAG, true),
			new SBBCodeParser_BBCode('hr', '<hr />', SBBCodeParser_BBCode::INLINE_TAG, true),

			new SBBCodeParser_BBCode('anchor', function($content, $attribs, $node)
			{
				if(empty($attribs['default']))
				{
					$attribs['default'] = $content;
					// remove the content for [anchor]test[/anchor]
					// usage as test is the anchor
					$content = '';
				}
			
				$attribs['default'] = preg_replace('/[^a-zA-Z0-9_\-]+/', '', $attribs['default']);

				return "<a name=\"{$attribs['default']}\">{$content}</a>";
			}),
			new SBBCodeParser_BBCode('goto', function($content, $attribs, $node)
			{
				if(empty($attribs['default']))
					$attribs['default'] = $content;

				$attribs['default'] = preg_replace('/[^a-zA-Z0-9_\-#]+/', '', $attribs['default']);

				return "<a href=\"#{$attribs['default']}\">{$content}</a>";
			}),
			new SBBCodeParser_BBCode('jumpto', function($content, $attribs, $node)
			{
				if(empty($attribs['default']))
					$attribs['default'] = $content;

				$attribs['default'] = preg_replace('/[^a-zA-Z0-9_\-#]+/', '', $attribs['default']);

				return "<a href=\"#{$attribs['default']}\">{$content}</a>";
			}),

			new SBBCodeParser_BBCode('img', function($content, $attribs, $node)
			{
				$attrs = '';
			
				// for when default attrib is used for width x height
				if(isset($attribs['default']) && preg_match("/[0-9]+[Xx\*][0-9]+/", $attribs['default']))
				{
					list($attribs['width'],$attribs['height']) = explode('x', $attribs['default']);
					$attribs['default'] = '';
				}
				// for when width & height are specified as the default attrib
				else if(isset($attribs['default']) && is_numeric($attribs['default']))
				{
					$attribs['width'] = $attribs['height'] = $attribs['default'];
					$attribs['default'] = '';
				}
			
				// add alt tag if is one
				if(isset($attribs['default']) && !empty($attribs['default']))
					$attrs .= " alt=\"{$attribs['default']}\"";
				else if(isset($attribs['alt']))
					$attrs .= " alt=\"{$attribs['alt']}\"";
				else
					$attrs .= " alt=\"{$content}\"";

				// width and height can only be numeric, anything else should be ignored to prevent XSS
				if(isset($attribs['width']) && is_numeric($attribs['width']))
					$attrs .= " width=\"{$attribs['width']}\"";
				if(isset($attribs['height']) && is_numeric($attribs['height']))
					$attrs .= " height=\"{$attribs['height']}\"";
	
				// add http:// to www starting urls
				if(strpos($content, 'www') === 0)
					$content = 'http://' . $content;
				// add the base url to any urls not starting with http or ftp as they must be relative
				else if(substr($content, 0, 4) !== 'http'
					&& substr($content, 0, 3) !== 'ftp')
					$content = $node->root()->get_base_uri() . $content;

				return "<img{$attrs} src=\"{$content}\" />";
			}, SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),	
			new SBBCodeParser_BBCode('email', function($content, $attribs, $node)
			{
				if(empty($attribs['default']))
					$attribs['default'] = $content;


				return "<a href=\"mailto:{$attribs['default']}\">{$content}</a>";
			}, SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL),
			new SBBCodeParser_BBCode('url', function($content, $attribs, $node)
			{
				if(empty($attribs['default']))
					$attribs['default'] = $content;

				// add http:// to www starting urls
				if(strpos($attribs['default'], 'www') === 0)
					$attribs['default'] = 'http://' . $attribs['default'];
				// add the base url to any urls not starting with http or ftp as they must be relative
				else if(substr($attribs['default'], 0, 4) !== 'http'
					&& substr($attribs['default'], 0, 3) !== 'ftp')
					$attribs['default'] = $node->root()->get_base_uri() . $attribs['default'];

				return "<a href=\"{$attribs['default']}\">{$content}</a>";
			}, SBBCodeParser_BBCode::INLINE_TAG, false, array(), array('text_node'), SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_ALL)
		);
	}

	/**
	 * Adds an emoticon to the parser.
	 * @param string $key
	 * @param string $url
	 * @param bool   $replace If to replace another emoticon with the same key
	 * @return bool
	 */
	public function add_emoticon($key, $url, $replace=true)
	{
		if(isset($this->emoticons[$key]) && !$replace)
			return false;
		
		$this->emoticons[$key] = $url;
		return true;
	}

	/**
	 * Adds multipule emoticons to the parser. Should be in
	 * emoticon_key => image_url format.
	 * @param Array $emoticons
	 * @param bool $replace If to replace another emoticon with the same key
	 */
	public function add_emoticons(Array $emoticons, $replace=true)
	{
		foreach($emoticons as $key => $url)
			$this->add_emoticon($key, $url, $replace);
	}

	/**
	 * Removes an emoticon from the parser.
	 * @param string $key
	 * @return bool
	 */
	public function remove_emoticon($key, $url)
	{
		if(!isset($this->emoticons[$key]))
			return false;
		
		unset($this->emoticons[$key]);
		return true;
	}

	/**
	 * Adds a bbcode to the parser
	 * @param SBBCodeParser_BBCode $bbcode
	 * @param bool $replace If to replace another bbcode which is for the same tag
	 * @return bool
	 */
	public function add_bbcode(SBBCodeParser_BBCode $bbcode, $replace=true)
	{
		if(!$replace && isset($this->bbcodes[$bbcode->tag()]))
			return false;
		
		$this->bbcodes[$bbcode->tag()] = $bbcode;
		return true;
	}
	
	/**
	 * Adds an array of SBBCodeParser_BBCode's to the document
	 * @param array $bbcodes
	 * @param bool $replace 
	 * @see add_bbcode
	 */
	public function add_bbcodes(Array $bbcodes, $replace=true)
	{
		foreach($bbcodes as $bbcode)
			$this->add_bbcode($bbcode, $replace);
	}
	
	/**
	 * Removes a BBCode from the document
	 * @param mixed $bbcode String tag name or SBBCodeParser_BBCode
	 */
	public function remove_bbcode($bbcode)
	{
		if($bbcode instanceof SBBCodeParser_BBCode)
			$bbcode = $bbcode->tag();
		
		unset($this->bbcodes[$bbcode]);
	}
	
	/**
	 * Gets an array of bbcode tags that will currently be processed
	 * @return array
	 */
	public function list_bbcodes()
	{
		return array_keys($this->bbcodes);
	}
	
	/**
	 * Returns the SBBCodeParser_BBCode object for the passed
	 * tag.
	 * @param string $tag
	 * @return SBBCodeParser_BBCode
	 */
	public function get_bbcode($tag)
	{
		if(!isset($this->bbcodes[$tag]))
			return null;
		
		return $this->bbcodes[$tag];
	}
	
	/**
	 * Gets the base URI to be used in links, images, ect.
	 * @return string 
	 */
	public function get_base_uri()
	{
		if($this->base_uri != null)
			return $this->base_uri;
		
		return htmlentities(dirname($_SERVER['PHP_SELF']), ENT_QUOTES | ENT_IGNORE, "UTF-8") . '/';
	}
	
	/**
	 * Sets the base URI to be used in links, images, ect.
	 * @param string $uri 
	 */
	public function set_base_uri($uri)
	{
		$this->base_uri = $uri;
	}
	
	/**
	 * Parses a BBCode string into the current document
	 * @param string $str
	 * @return SBBCodeParser_Document 
	 */
	public function parse($str)
	{
		$str      = preg_replace('/[\r\n|\r]/', "\n", $str);
		$len      = strlen($str);
		$tag_open = false;
		$tag_text = '';
		$tag      = '';
		
		// set the document as the current tag.
		$this->current_tag = $this;

		for($i=0; $i<$len; ++$i)
		{
			if($str[$i] === '[')
			{
				if($tag_open)
					$tag_text .= '[' . $tag;
				
				$tag_open = true;
				$tag      = '';
			}
			else if($str[$i] === ']' && $tag_open)
			{
				if($tag !== '')
				{
					$bits        = preg_split('/([ =])/', trim($tag), 2, PREG_SPLIT_DELIM_CAPTURE);
					$tag_attrs   = (isset($bits[2]) ? $bits[1] . $bits[2] : '');
					$tag_closing = ($bits[0][0] === '/');
					$tag_name    = ($bits[0][0] === '/' ? substr($bits[0], 1) : $bits[0]);

					if(isset($this->bbcodes[$tag_name]))
					{
						$this->tag_text($tag_text);
						$tag_text = '';

						if($tag_closing)
						{
							if(!$this->tag_close($tag_name))
								$tag_text = "[{$tag}]";
						}
						else
						{
							if(!$this->tag_open($tag_name, $this->parse_attribs($tag_attrs)))
								$tag_text = "[{$tag}]";
						}
					}
					else
						$tag_text .= "[{$tag}]";
				}
				else
					$tag_text .= '[]';
				
				$tag_open = false;
				$tag      = '';
			}
			else if($tag_open)
				$tag .= $str[$i];
			else
				$tag_text .= $str[$i];
		}
		
		$this->tag_text($tag_text);

		if($this->throw_errors && !$this->current_tag instanceof SBBCodeParser_Document)
			throw new SBBCodeParser_MissingEndTagException("Missing closing tag for tag [{$this->current_tag->tag()}]");
		
		return $this;
	}

	/**
	 * Handles a BBCode opening tag
	 * @param string $tag
	 * @param array $attrs
	 * @return bool 
	 */
	private function tag_open($tag, $attrs)
	{
		if($this->current_tag instanceof SBBCodeParser_TagNode)
		{
			$closing_tags = $this->bbcodes[$this->current_tag->tag()]->closing_tags();
		
			if(in_array($tag, $closing_tags))
				$this->tag_close($this->current_tag->tag());
		}
		
		if($this->current_tag instanceof SBBCodeParser_TagNode)
		{
			$accepted_children = $this->bbcodes[$this->current_tag->tag()]->accepted_children();

			if(!empty($accepted_children) && !in_array($tag, $accepted_children))
				return false;

			if($this->throw_errors && !$this->bbcodes[$tag]->is_inline()
				&& $this->bbcodes[$this->current_tag->tag()]->is_inline())
				throw new SBBCodeParser_InvalidNestingException("Block level tag [{$tag}] was opened within an inline tag [{$this->current_tag->tag()}]");
		}

		$node = new SBBCodeParser_TagNode($tag, $attrs);
		$this->current_tag->add_child($node);
		
		if(!$this->bbcodes[$tag]->is_self_closing())
			$this->current_tag = $node;
		
		return true;
	}
	
	/**
	 * Handles tag text
	 * @param string $text
	 * @return void
	 */
	private function tag_text($text)
	{
		if($this->current_tag instanceof SBBCodeParser_TagNode)
		{
			$accepted_children = $this->bbcodes[$this->current_tag->tag()]->accepted_children();

			if(!empty($accepted_children) && !in_array('text_node', $accepted_children))
				return;
		}
		
		$this->current_tag->add_child(new SBBCodeParser_TextNode($text));
	}
	
	/**
	 * Handles BBCode closing tag
	 * @param string $tag
	 * @return bool
	 */
	private function tag_close($tag)
	{
		if(!$this->current_tag instanceof SBBCodeParser_Document
			&& $tag !== $this->current_tag->tag())
		{
			$closing_tags = $this->bbcodes[$this->current_tag->tag()]->closing_tags();

			if(in_array($tag, $closing_tags) || in_array('/' . $tag, $closing_tags))
				$this->current_tag = $this->current_tag->parent();
		}
		
		if($this->current_tag instanceof SBBCodeParser_Document)
			return false;
		else if($tag !== $this->current_tag->tag())
		{
			// check if this is a tag inside another tag like
			// [tag1] [tag2] [/tag1] [/tag2]
			$node = $this->current_tag->find_parent_by_tag($tag);
			
			if($node !== null)
			{
				$this->current_tag = $node->parent();
				
				while(($node = $node->last_tag_node()) !== null)
				{
					$new_node = new SBBCodeParser_TagNode($node->tag(), $node->attributes());
					$this->current_tag->add_child($new_node);
					$this->current_tag = $new_node;
				}
			}
			else
				return false;
		}
		else
			$this->current_tag = $this->current_tag->parent();
		
		return true;
	}
	
	/**
	 * Parses a bbcode attribute string into an array
	 * @param string $attribs
	 * @return array
	 */
	private function parse_attribs($attribs)
	{
		$ret     = array('default' => null);
		$attribs = trim($attribs);

		if($attribs == '')
			return $ret;

		// if this tag only has one = then there is only one attribute
		// so add it all to default
		if($attribs[0] == '=' && strrpos($attribs, '=') === 0)
			$ret['default'] = htmlentities(substr($attribs, 1), ENT_QUOTES | ENT_IGNORE, "UTF-8");
		else
		{
			preg_match_all('/(\S+)=((?:(?:(["\'])(?:\\\3|[^\3])*?\3))|(?:[^\'"\s]+))/',
				$attribs,
				$matches,
				PREG_SET_ORDER);

			foreach($matches as $match)
				$ret[$match[1]] = htmlentities($match[2], ENT_QUOTES | ENT_IGNORE, "UTF-8");
		}

		return $ret;
	}
	
	private function loop_text_nodes($func, array $exclude=array(), SBBCodeParser_ContainerNode $node=null)
	{
		if($node === null)
			$node = $this;
		
		foreach($node->children() as $child)
		{
			if($child instanceof SBBCodeParser_TagNode)
			{
				if(!in_array($child->tag(), $exclude))
					 $this->loop_text_nodes($func, $exclude, $child);
			}
			else if($child instanceof SBBCodeParser_TextNode)
			{
				$func($child);
			}
		}
	}

	/**
	 * Detects any none clickable links and makes them clickable
	 * @return SBBCodeParserDdocument
	 */
	public function detect_links()
	{
		$this->loop_text_nodes(function($child) {
			preg_match_all("/(?:(?:https?|ftp):\/\/|(?:www|ftp)\.)(?:[a-zA-Z0-9\-\.]{1,255}\.[a-zA-Z]{1,20})(?::[0-9]{1,5})?(?:\/[^\s'\"]*)?(?:(?<![,\)\.])|[\S])/",
			$child->get_text(),
			$matches,
			PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE);

			if(count($matches[0]) == 0)
				return;

			$replacment = array();
			$last_pos   = 0;

			foreach($matches[0] as $match)
			{
				if(substr($match[0], 0, 3) === 'ftp' && $match[0][3] !== ':')
					$url = 'ftp://' . $match[0];
				else if($match[0][0] === 'w')
					$url = 'http://' . $match[0];
				else
					$url = $match[0];

				$url      = new SBBCodeParser_TagNode('url', array('default' => htmlentities($url, ENT_QUOTES | ENT_IGNORE, "UTF-8")));
				$url_text = new SBBCodeParser_TextNode($match[0]);
				$url->add_child($url_text);

				$replacment[] = new SBBCodeParser_TextNode(substr($child->get_text(), $last_pos, $match[1] - $last_pos));
				$replacment[] = $url;
				$last_pos = $match[1] + strlen($match[0]);
			}

			$replacment[] = new SBBCodeParser_TextNode(substr($child->get_text(), $last_pos));
			$child->parent()->replace_child($child, $replacment);
		}, $this->get_excluded_tags(SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_URL));

		return $this;
	}

	/**
	 * Detects any none clickable emails and makes them clickable
	 * @return SBBCodeParserDdocument
	 */
	public function detect_emails()
	{
		$this->loop_text_nodes(function($child) {
			preg_match_all("/(?:[a-zA-Z0-9\-\._]){1,}@(?:[a-zA-Z0-9\-\.]{1,255}\.[a-zA-Z]{1,20})/",
				$child->get_text(),
				$matches,
				PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE);

			if(count($matches[0]) == 0)
				return;

			$replacment = array();
			$last_pos   = 0;

			foreach($matches[0] as $match)
			{
				$url      = new SBBCodeParser_TagNode('email', array());
				$url_text = new SBBCodeParser_TextNode($match[0]);
				$url->add_child($url_text);

				$replacment[] = new SBBCodeParser_TextNode(substr($child->get_text(), $last_pos, $match[1] - $last_pos));
				$replacment[] = $url;
				$last_pos = $match[1] + strlen($match[0]);
			}

			$replacment[] = new SBBCodeParser_TextNode(substr($child->get_text(), $last_pos));
			$child->parent()->replace_child($child, $replacment);
		}, $this->get_excluded_tags(SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_EMAIL));
		
		return $this;
	}
	
	/**
	 * Detects any emoticons and replaces them with their images
	 * @return SBBCodeParserDdocument
	 */
	public function detect_emoticons()
	{
		$pattern = '';
		foreach($this->emoticons as $key => $url)
			$pattern .= ($pattern === ''? '/(?:':'|') . preg_quote($key, '/');
		$pattern .= ')/';
		
		$emoticons = $this->emoticons;

		$this->loop_text_nodes(function($child) use ($pattern, $emoticons) {
			preg_match_all($pattern,
				$child->get_text(),
				$matches,
				PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE);

			if(count($matches[0]) == 0)
				return;

			$replacment = array();
			$last_pos   = 0;

			foreach($matches[0] as $match)
			{
				$url      = new SBBCodeParser_TagNode('img', array('alt'=>$match[0]));
				$url_text = new SBBCodeParser_TextNode($emoticons[$match[0]]);
				$url->add_child($url_text);

				$replacment[] = new SBBCodeParser_TextNode(substr($child->get_text(), $last_pos, $match[1] - $last_pos));
				$replacment[] = $url;
				$last_pos = $match[1] + strlen($match[0]);
			}

			$replacment[] = new SBBCodeParser_TextNode(substr($child->get_text(), $last_pos));
			$child->parent()->replace_child($child, $replacment);
		}, $this->get_excluded_tags(SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_EMOTICON));
		
		return $this;
	}

	/**
	 * Gets an array of tages to be excluded from
	 * the elcude param
	 * @param int $exclude What to gets excluded from i.e. SBBCodeParser_BBCode::AUTO_DETECT_EXCLUDE_EMOTICON
	 * @return array
	 */
	private function get_excluded_tags($exclude)
	{
		$ret = array();

		foreach($this->bbcodes as $bbcode)
			if($bbcode->auto_detect_exclude() & $exclude)
				$ret[] = $bbcode->tag();

		return $ret;
	}
}
