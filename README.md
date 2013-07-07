# SBBCodeParser v1.1
Copyright (C) 2011, Sam Clarke (http://www.samclarke.com)

SBBCodeParser is a simple BBCode parser.

### Example usage:

	$parser = new \SBBCodeParser\Node_Container_Document();

	$parser->add_emoticons(array(
		':)' => 'http://localhost/Classes/SCEditor-punbb/punbb-1.3.5/img/smilies/smile.png',
		'=)' => 'http://localhost/Classes/SCEditor-punbb/punbb-1.3.5/img/smilies/smile.png'
	));

	echo $parser->parse('This should be [b]bold[/b] and this should be [i]italic[/i]')
		->detect_links()
		->detect_emails()
		->detect_emoticons()
		->get_html();
		
### Example of adding a custom BBCode:

	$bbcode = new \SBBCodeParser\BBCode('youtube', function($content, $attribs)
	{
		if(substr($content, 0, 23) === 'http://www.youtube.com/')
			$uri = $content;
		else
			$uri = 'http://www.youtube.com/v/' . $content;

		return '';
	}, \SBBCodeParser\BBCode::BLOCK_TAG, false, array(), array('text_node'), \SBBCodeParser\BBCode::AUTO_DETECT_EXCLUDE_ALL);

	$parser->add_bbcode($bbcode);
	
### Currently included default BBCodes:

	b
	i
	strong
	em
	u
	s
	blink
	sub
	sup
	ins
	del
	right
	left
	center
	justify
	note
	hidden
	abbr
	acronym
	icq
	skype
	bing
	google
	wikipedia
	youtube
	vimeo
	flash
	paypal
	pastebin
	gist
	twitter
	tweets
	googlemaps
	pdf
	scribd
	spoiler
	tt
	pre
	code
	php
	quote
	font
	size
	color
	list
	ul
	ol
	li
	*
	table
	th
	h
	tr
	row
	r
	td
	col
	c
	notag
	nobbc
	noparse
	h1
	h2
	h3
	h4
	h5
	h6
	big
	small
	br
	sp
	hr
	anchor
	goto
	jumpto
	img
	email
	url

# License

SBBCodeParser is licensed under the LGPL license:
http://www.gnu.org/licenses/lgpl.html
