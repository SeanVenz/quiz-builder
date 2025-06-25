=== Quiz Builder ===
Contributors: pikocode
Tags: quiz, assessment, results, pdf, education
Requires at least: 6.2
Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight and flexible quiz plugin that allows you to create engaging quizzes with complete control over questions, answers, and results.

== Description ==

Quiz Builder is a comprehensive quiz plugin designed for educators, trainers, and content creators who want to create engaging quizzes with ease. Perfect for educational websites, training programs, and interactive content.

**Key Features:**

* Create unlimited quizzes with custom questions
* Multiple choice questions with point-based scoring
* **Comprehensive onboarding wizard** - Step-by-step setup guide
* **Dedicated results pages** - Use `[quiz_results]` shortcode for result display
* Randomize questions and answers for unique experiences
* Track quiz attempts and view detailed results
* Export quiz results to CSV format
* **PDF export functionality** - Professional quiz result downloads
* Responsive design works on all devices
* User-friendly admin interface
* Guest user support (no registration required)
* Detailed results page with answer breakdowns
* Quiz pagination options
* Categories for better organization

**Perfect For:**

* Educational institutions
* Training programs
* Assessment tools
* Interactive content
* Knowledge testing
* Student evaluation

**How It Works:**

1. **Easy Setup**: Use the comprehensive onboarding wizard
   - Create your quiz with an engaging title and description
   - Add multiple-choice questions with custom point values
   - Get instant shortcode instructions for both quiz and results
2. Configure quiz settings (randomization, pagination, etc.)
3. **Display Your Quiz**: Use `[quiz_builder quiz_id="1"]` shortcode anywhere
4. **Display Results**: Create a results page with `[quiz_results]` shortcode
5. View and export results from the admin dashboard
6. **PDF Export**: Enable PDF downloads for professional result sharing

The plugin is designed to be lightweight yet powerful, giving you full control over your quiz content without unnecessary bloat.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/quiz-builder` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to Quiz Builder in your WordPress admin menu
4. **Follow the Getting Started wizard** - Complete 3-step onboarding process
5. Use the provided shortcodes:
   - `[quiz_builder quiz_id="X"]` to display quizzes
   - `[quiz_results]` to display quiz results on a dedicated page

== Frequently Asked Questions ==

= How do I create a quiz? =

Navigate to Quiz Builder > Getting Started in your WordPress admin area. The plugin includes a step-by-step guide to help you create your first quiz.

= How do I display a quiz on my website? =

Use the shortcode `[quiz_builder quiz_id="1"]` (replace "1" with your quiz ID) in any post, page, or widget area where you want the quiz to appear. The onboarding wizard provides you with the exact shortcode after quiz creation.

= How do I display quiz results? =

Create a separate page (e.g., "Quiz Results") and add the `[quiz_results]` shortcode. This will automatically display results for users who have completed quizzes. For custom PHP themes, use `<?php echo do_shortcode('[quiz_results]'); ?>`.

= How do I display a quiz on my website? =

Use the shortcode [quiz_builder quiz_id="1"] (replace "1" with your quiz ID) in any post, page, or widget area where you want the quiz to appear.

= Can users take quizzes without registering? =

Yes! The plugin supports both registered users and guest users. Anyone can take your quizzes without needing to create an account.

= How do I view quiz results? =

Go to Quiz Builder > View Results in your admin area to see all quiz attempts, scores, and detailed answers.

= Can I export quiz results? =

Yes, you can export all quiz results to a CSV file for further analysis or record keeping.

= Can I randomize questions and answers? =

Yes! You can enable question randomization and answer randomization in the quiz settings to create unique experiences for each user.

= Is the plugin mobile-friendly? =

Absolutely! The quiz interface is fully responsive and works perfectly on mobile devices, tablets, and desktops.

= Can I create multiple quizzes? =

Yes, you can create unlimited quizzes with the plugin. Each quiz can have its own questions, settings, and configurations.

= Does the plugin have a setup wizard? =

Yes! The plugin includes a comprehensive 3-step onboarding wizard that guides you through:
1. Creating your quiz with title and description
2. Adding questions with multiple-choice answers
3. Configuring answer options and point values
The wizard also provides copy-paste instructions for displaying both your quiz and results pages.

= Can I export quiz results to PDF? =

Yes! Enable the "Allow PDF Export" setting for individual quizzes, and users will see a "Download PDF Results" button on their results page.

== Screenshots ==

1. Quiz creation interface - Easy-to-use admin panel for creating quizzes
2. Question management - Add and organize quiz questions with multiple choice answers
3. Quiz settings - Configure randomization, pagination, and display options
4. Frontend quiz display - Clean, responsive quiz interface for users
5. Results dashboard - View and manage all quiz attempts and scores
6. Detailed results - Individual quiz attempt details with answer breakdown

== Changelog ==

= 1.0.0 =
* Initial release
* **Comprehensive onboarding wizard** - 3-step setup process
* Create unlimited quizzes with custom questions
* Multiple choice questions with point-based scoring
* **Quiz results shortcode** - `[quiz_results]` for dedicated results pages  
* **PDF export functionality** - Professional quiz result downloads
* Quiz randomization options
* Results tracking and CSV export
* Responsive frontend design
* Admin dashboard for quiz management
* Guest user support
* **Dual shortcode support** - Both Gutenberg blocks and PHP themes
* Copy-to-clipboard functionality for easy shortcode usage
* Getting started guide with instant setup instructions

== Upgrade Notice ==

= 1.0.0 =
Initial release of Quiz Builder plugin. Start creating engaging quizzes for your website today!

== Support ==

For support questions, please use the WordPress.org support forums for this plugin. We monitor the forums regularly and will do our best to help you resolve any issues.

== Privacy Policy ==

This plugin stores quiz attempt data including:
- User responses to quiz questions
- Scores and completion times
- IP addresses for guest users (for session management)
- User IDs for registered users

This data is stored locally in your WordPress database and is not transmitted to external services. You can export or delete this data at any time through the plugin's admin interface.
