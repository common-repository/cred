=== Cred Micropayments ===
Contributors: tobykurien
Tags: cred, micropayments, monetize, monetise, paywall, payment, blog, sell, ebooks, e-book, pdf, mp3, music, videos, ezine, e-zine, digital content, billing, premium, freemium, subscription, paid subscription 
Requires at least: 2.9
Tested up to: 3.0.1
Stable tag: trunk

Cred is a payment system for Wordpress that allows you to monetize your content or digital media 
(such as e-books, music or videos) through micropayments or subscriptions. It is very simple and 
easy for both the content provider and the user to use, and payments can be less than $1 (USD).

== Description ==

Install this plugin and start earning revenue! Cred is a payment system for Wordpress that allows 
you to monetize your content or digital media (such as e-books, music or videos) through micropayments 
or subscriptions. It is very simple and easy for both the content provider and the user to use, and payments 
can be less than $1 (USD).

All sites hooked up through the Cred system use one payment system and currency meaning your potential customers 
will not need to signup to every Wordpress paywall site and rather be able to purchase from multiple 
sites with one account.

Cred has an open API which allows it to be integrated with mutiple content management systems, including 
proprietary (in-house) solutions. View the API documentation at http://yourcred.com/api_doc

Use Cred to implement your site's paywall, implement a freemium model, sell a subscription to your e-zine,  
and have an automated billing system with statements and analytics. 

The following video shows you how easy it is to add premium content to your blog with the Cred plugin: 
http://www.youtube.com/watch?v=GIXX-gKoCwc

For more information, videos, examples, and to sign up, please visit http://yourcred.com

== Installation ==

If you are not installing Cred from the Wordpress repository the instructions are as follows:

1. Download the "Cred.zip" file from https://yourcred.com/plugins/wordpress
2. Upload the Cred folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. If you are not signed up as a content provider go to https://yourcred.com/welcome/provider_signup_form. 
   Once signed up and activated, you can get your API key at the bottom of your dashboard page.
5. Enter the API key in the 'Plugins' menu in Wordpress under 'Cred options'. You are now ready to 
   start using Cred. Watch the video to see how it works:  https://yourcred.com/plugins/wordpress

== Screenshots ==

1. A Cred user's dashboard
2. The Cred purchase screen
3. Cred users can view their full statement
4. As a content provider, you get a dashboard too where you can view your most popular content and revenue

== Frequently Asked Questions ==

Go to https://yourcred.com/faq/provider_faq for a full list of general questions and answers. 
Here are some plugin specific FAQ's:

= I have installed the plugin and made articles "paid for", yet I still see the full article. Is this plugin broken? =

  If you have added an article and then gone to the front-end to view it, it is likely that you 
  are still logged in as editor/administrator. All editors/administrators can view their content 
  without having to pay for it, so the Cred purchase link is not displayed. Try logging out 
  before previewing the content on the site.

= How do I place a Cred logo into the title for an article that is flagged as paywalled? =
  Edit the template you are using and add this to display the logo of the appropriate size:
  
       <?php echo $cred_title_icon_[small|medium|large]; ?>
       
     For example, to use the small icon:
     
       <?php echo $cred_title_icon_small; ?>
       
     When the plugin is disabled, this will simply echo blank.
  
= My template is displaying an excerpt from the full content, or the full content itself, instead of the actual excerpt with a link to redeem the content. How do I fix this? =

   Most likely your template is using the get_the_content() function of WordPress which does
     not apply any content filters. In this case, simply replace occurrences of 
         get_the_content(...)
     with:     
         apply_filters('the_content',get_the_content( ... ))      
     This should then apply the cred content filter.

== Changelog ==
= 1.0 =
* Ability to load the API key from Cred dashboard
* Cred logo preview in config screen
* Text, stylesheet changes

= 0.8.5 =
* Error message fix

= 0.8.4 =
* Code documentation

= 0.8.3 =
* Allow 0 cred price up to and including 100 cred

= 0.8.1 =
* Bug fix, v0.8.1.1 and v0.8.2 have no code changes, just problems with versioning

= 0.8 =
* Added ability to select logo sizes (stylesheet fix for larger sizes coming soon)
* Added ability to upload multimedia files for Cred purchase. These files are secure.
* Various fixes and improvements (e.g. removal of meta data from post edit page)

= 0.7.2 =
* Fixed popup centering

= 0.7.1 =
* Fixed incorrect server URL resulting in errors about not being able to contact Cred server

= 0.7 =
* Moved loading of Cred scripts/styles to pages containing Cred content only

= 0.6 =
* Release candidate

= 0.5 =
* Subscriptions added

= 0.4 =
* User interface on public website changed

= 0.3 =
* Uses new authentication mechanism based on one-off tokens

== Upgrade Notice ==

= 0.7 =
Fixes incompatability with some themes

= 0.6 =
This version includes final tweaks for a production site

= 0.5 =
This version implements the user subscription functionality

