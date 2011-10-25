=== Talkback ===
Contributors: baptiste.gourdin,ebursztein
Tags: comments, spam, linkback, pingback, trackback
Requires at least: 2.0.2
Tested up to: 3.2.1
Stable tag: 1.3.1

TalkBack is a secure protocol for LinkBack notification designed to prevent the blogosphere from being spammed.

== Description ==

TalkBack is a open-source secure LinkBack mechanism based on a public-key cryptography that aims at tackling the blog spam problem at its root: instead of detecting spam via the content analysis like Akismet, TalkBack prevents spammers from posting LinkBack notifications. When installed TalkbBack add the two following lines of defense to your blog:

- The first line of defense is a lightweight PKI (Public Key Infrastructure) that ensures the identity of blogs by using public key cryptography. Moreover using cryptography allows TalkBack to guarantee that no one can tampers with your TalkBack notifications and that the receiver will received them unaltered.

- The second line of defense, is a global rate limiting system enforced by the TalkBack authorities that ensures that with a single blog identity, a spammer cannot massively spam TalkBack aware blogs.


TalkBack is compatible with every other defense mechanism and do not store/disclose any private information about your blog or you. As a matter of fact if you don't trust the network, TalkBack can also encrypt your notification to protect them from eavesdroppers.

A complete description of Talkback is available here: http://code.google.com/p/talkback-client/downloads/detail?name=talkback-paper.pdf
and the source code here: http://code.google.com/p/talkback-client/downloads/detail?name=talkback-wordpress-plugin.pdf

== Installation ==

1. Upload `talkback` directory to the `/wp-content/plugins/` directory
2. Verify that the `talkback/work` directory is not browsable from the web.
3. Activate the plugin through the 'Plugins' menu in WordPress


== Frequently Asked Questions ==

== Changelog ==


