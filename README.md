CONTENTS OF THIS FILE
---------------------

* Introduction
* Installation
* Configuration
* Author

INTRODUCTION
------------

This plugin enable Mautic 5 to run Mailjet as a email transport.


INSTALLATION
------------

1. Get the plugin using `composer require shinde-rahul/plugin-mailjet`
2. Install it using `php bin\console mautic:plugins:reload`.
3. The plugin will start listing on plugin page. ![Plugins Page](Docs/imgs/01%20-%20Plugins%20Page.png)


CONFIGURATION
-------------

Be sure to use the `mautic+mailjet+smtp` as Data Source Name, or DSN.
The following is the example for the DSN.
`'mailer_dsn' => 'mautic+mailjet+smtp://<apiKey>:<secretKey>@default:465',`

Follow the steps tp setup Mailjet SMTP DSN,
1. Navigate to Configuration (/s/config/edit>)
2. Scroll to Email Settings
3. Update the following fields leaving rest default or empty,

|  Field    | Value                  |
|-----------|------------------------|
| Scheme    |  `mautic+mailjet+smtp` |
| Host      |  `default`             |
| Port      |  `465`                 |
| User      |  `<apiKey>`            |
| Password  |  `<secretKey>`         |

The `<apiKey>` and `<secretKey>` will be used for authentication purposes. Please visit the [Mailjet Guide][MailjetGuidePage]

On the Configuration page **Email DSN** should look like ![Email DSN](Docs/imgs/02%20-%20Email%20DSN.png "Email DSN") 


AUTHOR
------

👤 **Rahul Shinde**

- Twitter: [@_rahulshinde](https://twitter.com/_rahulshinde)
- Github: [@shinde-rahul](https://github.com/shinde-rahul)


[MailjetGuidePage]: <https://dev.mailjet.com/email/guides/getting-started/>

