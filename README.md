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

The following configurations can be set:

|Configuration|Description|Default value|Example values|
|----|----|----|----|
|application_type|(optional)Sets the application to the required type|\Skeleton\Application\Web|This must be set to \Skeleton\Application\Web|
|hostnames|(required)an array containing the hostnames to listen for. Wildcards can be used via `*`.| []| [ 'www.example.be, '*.example.be' ]|
|routes|Array with route information|[]| See routes |
|module_default|The default module to search for|'index'||
|module_404|The 404 module on fallback when no module is found|'404'||
|sticky_pager|Enable sticky pager|false|Only available if [skeleton-pager](https://github.com/tigron/skeleton-pager) is installed|
|route_resolver|Closure to provide module resolving based on requested path|Internal module resolver||
|csrf_enabled|Enable CSRF|false|true/false|
|replay_enabled|Prevent replay attack|false|true/false|



#### Handling of media files

If the requested url contains an extension which matches a known media type, the
requested file will be served from the `media/` directory of the application.

If the requested media file could not be found, `skeleton-core` will search for
a matching file in the folder specified by configuration directive `asset_dir`
(if any).

### routes

An array which maps `routes` to `modules`. A route definition cab be used to
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

