# skeleton-application-web
This skeleton application will create a web application. The web application
will have modules, templates and events.

## Installation

Installation via composer:

    composer require tigron/skeleton-application-web

## Setup the application

Your web application should follow the following directory structure:

    - application_path from skeleton-core
      - APP_NAME
        - config
		- event
		- media
		  - css
		  - font
		  - image
		  - javascript
		- module
		- template

It is important to understand that every class that is created should be in
their correct namespace. The following namespaces should be used:

    module: \App\{APP_NAME}\Module
    event: \App\{APP_NAME}\Event

## Configuration

For this application, the [skeleton-core](https://github.com/tigron/skeleton-core)
Application configuration is required. Additional configuration can be found
below:

|Configuration|Description|Default value|Example values|
|----|----|----|----|
|routes|Array with route information|[]| See routes |
|module_default|The default module to search for|'index'||
|sticky_pager|Enable sticky pager|false|Only available if [skeleton-pager](https://github.com/tigron/skeleton-pager) is installed|
|route_resolver|Closure to provide module resolving based on requested path|Internal module resolver||
|csrf_enabled|Enable CSRF|false|true/false|
|replay_enabled|Prevent replay attack|false|true/false|

### routes

An array which maps `routes` to `modules`. A route definition can be used to
generate pretty URL's, or even translated versions. Usage is best described by
an example.

    [
        'web_module_index' => [
            '$language/default/route/to/index',
            '$language/default/route/to/index/$action',
            '$language/default/route/to/index/$action/$id',
            '$language[en]/test/routing/engine',
            '$language[en]/test/routing/engine/$action',
            '$language[en]/test/routing/engine/$action/$id',
        ],
    ],

#### Usage

##### Routing to the correct application

Based on the `Host`-header in the request, the correct application will be
started. This is where the `hostnames` array in the application's configuration
file (shown above) will come into play.

If `skeleton-core` could find a matching application based on the `Host`-header
supplied in the request, this is the application that will be started.

If your application has `base_uri` configured, that will be taken into account
as well. For example: the application for a CMS can be distinguished by setting
its `base_uri` to `/admin`.

##### Routing to the correct module

Requests that do not have a file extension and thus do not match a `media` file,
will be routed to a module and a matching method. The module is determined based
on the request URI, excluding all $_GET parameters. The module is a class that
should be derived from `\Skeleton\Core\Application\Web\Module`.

This can be best explained with some examples:

| requested uri    | classname                  | filename             |
| ---------------- | -------------------------- | -------------------- |
| /user/management | \App\APP_NAME\Module\User\Management | /user/management.php |
| /                | \App\APP_NAME\Module\Index           | /index.php           |
| /user            | \App\APP_NAME\Module\User            | /user.php            |
| /user            | \App\APP_NAME\Module\User\Index      | /user/index.php      |

As you can see in the last two examples, the `index` modules are a bit special,
in that they can be used instead of the underlying one if they sit in a
subfolder. The `index` is configurable via configuration directive
`module_default`

#### Routing to the correct method

A module can contain multiple methods that can handle the request. Each of
those requests have a method-name starting with 'display'. The method is defined
based on the $_GET['action'] variable.

Some examples:

| requested uri    | classname           | method               |
| -------------    | ---------           | --------             |
| /user            | \App\APP_NAME\Module\User     | display()            |
| /user?action=test| \App\APP_NAME\Module\User     | display_test()       |


### CSRF

The `skeleton-application-web` package can take care of automatically injecting
and validating CSRF tokens for every `POST` request it receives. Various events
have been defined, with which you can control the CSRF flow. A list of these
events can be found further down.

CSRF is disabled globally by default. If you would like to enable it, simply
flip the `csrf_enabled` flag to true, via configuration directive `csrf_enabled`

Once enabled, it is enabled for all your applications. If you want to disable it
for specific applications only, flip the `csrf_enabled` flag to `false` in the
application's configuration.

Several events are available to control the CSRF behaviour, these have been
documented below.

When enabled, hidden form elements with the correct token as a value will
automatically be injected into every `<form>...</form>` block found. This allows
for it to work without needing to change your code.

If you need access to the token value and names, you can access them from the
`env` variable which is automatically assigned to your template. The available
variables are listed below:

- env.csrf_header_token_name
- env.csrf_post_token_name
- env.csrf_session_token_name
- env.csrf_token

One caveat are `XMLHttpRequest` calls (or `AJAX`). If your application is using
`jQuery`, you can use the example below to automatically inject a header for
every relevant `XMLHttpRequest`.

First, make the token value and names available to your view. A good place to do
so, might be the document's `<head>...</head>` block.

    <!-- CSRF token values -->
    <meta name="csrf-header-token-name" content="{{ env.csrf_header_token_name }}">
    <meta name="csrf-token" content="{{ env.csrf_token }}">

Next, we can make use of `jQuery`'s `$.ajaxSend()`. This allows you to
configure settings which will be applied for every subsequent `$.ajax()` call
(or derivatives thereof, such as `$.post()`).

    $(document).ajaxSend(function(e, xhr, settings) {
        if (!(/^(GET|HEAD|OPTIONS|TRACE)$/.test(settings.type)) && !this.crossDomain) {
		    xhr.setRequestHeader($('meta[name="csrf-header-token-name"]').attr('content'), $('meta[name="csrf-token"]').attr('content'));
		}
    });

Notice the check for the request type and cross domain requests. This avoids
sending your token along with requests which don't need it.

### Replay

The built-in replay detection tries to work around duplicate form submissions by
users double-clicking the submit button. Often, this is not caught in the UI.

Replay detection is disabled by default, if you would like to enable it, flip
the `replay_enabled` configuration directive to true.

You can disable replay detection for individual applications by setting the
`replay_enabled` flag to `false` in their respective configuration.

When the replay detection is enabled, it will inject a hidden `__replay-token`
element into every `form` element it can find. Each token will be unique. Once
submited, the token is added to a list of tokens seen before. If the same token
appears again within 30 seconds, the replay detection will be triggered.

If your application has defined a `replay_detected` event, this will be called.
It is up to the application to decide what action to take. One suggestion is to
redirect the user to the value HTTP referrer, if present.

## Events

Events can be created to perform a task at specific key points during the
application's execution. This application supports all available events
described in [skeleton-core](https://github.com/tigron/skeleton-core).
Additionally, the following events are available:

### I18n context

#### get_translator_extractor

Get a Translator\Extractor for this application. If not provided, a
Translator\Extractor\Twig is created for the template-directory of the
application.

	public function get_translator_extractor(): \Skeleton\I18n\Translator\Extractor

#### get_translator_storage

Get a Translator\Storage for this application. If not provided, a
Translator\Storage\Po is created, but only if a default storage path is
configured.

	public function get_translator_storage(): \Skeleton\I18n\Translator\Storage

#### get_translator

Get a Translator object for this application. If no translation is needed,
return null. By default, a translator is created with the storage and
extractor of the above methods.

	public function get_translator(): ?\Skeleton\I18n\Translator

#### detect_language

Detect the language for the application. This is done in 3 steps:
  1) Is a language requested via $_GET['language']
  2) Is a language stored in $_SESSION['language']
  3) Negotiate a language between $_SERVER['HTTP_ACCEPT_LANGUAGE'] and all
  available languages.

The returned language will be stored in session.

	public function detect_language(): \Skeleton\I18n\LanguageInterface


### Module context

#### bootstrap

The `bootstrap` method is called before starting the module.

    public function bootstrap(\Skeleton\Core\Web\Module $module): void

#### teardown

The `teardown` method is called when the module is finished.

    public function bootstrap(\Skeleton\Core\Web\Module $module): void

#### access_denied

The `access_denied` method is called whenever a module is requested which can
not be accessed by the user. The optional `secure()` method in the module
indicates whether the user is granted access or not.

	public function access_denied(\Skeleton\Core\Web\Module $module): void

#### not_found

The `not_found` method is called whenever a module is requested which does not
exist.

	public function not_found(): void

### Rewrite context

#### reverse

The `reverse` method performs a reverse rewrite of the rendered html.
By default, this event will search for any link in the rendered html and calls
the `reverse_uri` method.

    public function reverse(string $html): string

#### reverse_uri

The `reverse_uri` method receives any url as input. The output should be a
a url which is rewritten. By default, this method will try to rewrite the
url based on the routes in the application configuration.

    public function reverse_uri(string $uri): string

#### reverse_uri_route_parameters

The `reverse_uri_route_parameters` method returns a fixed set of parameters that
can be used by `reverse_uri` to rewrite the given uri. 
By default, this method returns the following array:

    [
       'language' => $application->language->name_short
    ]

Because of this, any route can contain the $language-variable.
eg

    <a href="/user?action=edit">User</a>

will be rewritten as

    <a href="/nl/user?action=edit">User</a>

if the following route is created

    $language/user

definition:

    protected function reverse_uri_route_parameters(): array {	


### Security context

#### csrf_validate_enabled

The `csrf_validate_enabled` method overrides the complete execution of the
validation, which useful to exclude specific paths. An example implementation
can be found below.

    public function csrf_validate_enabled(): bool {
        $excluded_paths = [
            '/no/csrf/*',
        ];

        foreach ($excluded_paths as $excluded_path) {
            if (fnmatch ($excluded_path, $_SERVER['REQUEST_URI']) === true) {
                return false;
            }
        }

        return true;
    }

#### csrf_validate_success

The `csrf_validate_success` method allows you to override the check result after
a successful validation. It expects a boolean as a return value.

	public function csrf_validate_success(): bool


#### csrf_validate_failed

The `csrf_validate_failed` method allows you to override the check result
after a failed validation. It expects a boolean as a return value.

	public function csrf_validate_failed(): bool


#### csrf_generate_session_token

The `csrf_generate_session_token` method allows you to override the generation
of the session token, and generate a custom value instead. It expects a string
as a return value.

	public function csrf_generate_session_token(): string


#### csrf_inject

The `csrf_inject` method allows you to override the automatic injection of the
hidden CSRF token elements in the HTML forms of the rendered template. It
expects a string as a return value, containing the rendered HTML to be sent back
to the client.

	public function csrf_inject($html, $post_token_name, $post_token): string


#### csrf_validate

The `csrf_validate` method allows you to override the validation process of the
CSRF token. It expects a boolean as a return value.

	public function csrf_validate($submitted_token, $session_token): bool


#### replay_detected

The `replay_detected` method allows you to catch replay detection events. For
example, you could redirect the user to the value of the HTTP referrer header
if it is present:

    public function replay_detected() {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            Session::redirect($_SERVER['HTTP_REFERER'], false);
        } else {
            Session::redirect('/');
        }
    }

#### replay_inject

The `replay_inject` method allows you to override the automatic injection of the
hidden replay token elements in the HTML forms of the rendered template. It
expects a string as a return value, containing the rendered HTML to be sent back
to the client.

	public function csrf_inject($html, $post_token_name, $post_token): string

#### session_cookie

The `session_cookie` method allows you to set session cookie parameters before
the session is started. Typically, this would be used to SameSite cookie
attribute.

	public function session_cookie(): void

