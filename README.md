# DEPRECATED Plugin: Mailjet Adapter by Leuchtfeuer



## Overview / Purpose / Features
This plugin enable Mautic 5 to run Mailjet as a email transport, including bulk sending via API, and catching bounces and other feedback via Webhook.

## Requirements for this release
- Mautic 5.x (minimum 5.1)
- PHP 8.0 or higher

## Installation
### Composer
This plugin can be installed through composer.
### Manual Installation
Alternatively, it can be installed manually, following the usual steps:
- Download the plugin
- Unzip to the Mautic `plugins` directory
- Rename folder to `LeuchtfeuerMailjetAdapterBundle`
- In the Mautic backend, go to the `Plugins` page as an administrator
- Click on the `Install/Upgrade Plugins` button to install the Plugin.
OR
- If you have shell access, execute `php bin\console cache:clear` and `php bin\console mautic:plugins:reload` to install the plugins.


## Configuration

### Mautic 

This plugin provide two transports,
1. SMTP Relay:
   The best and fastest way to use the SMTP Relay is to have your own local mail server relaying messages to the Mailjet SMTP.
2. Email API:
   The Mailjet API is organized around REST. For more visit [Send API v3.1][SendApiV31Home].

|            | DSN (Data Source Name)                                                      | 
|------------|-----------------------------------------------------------------------------|
| SMTP Relay | `'mailer_dsn' => 'mautic+mailjet+smtp://<apiKey>:<secretKey>@default:465',` |
| Email API  | `'mailer_dsn' => 'mautic+mailjet+api://<apiKey>:<secretKey>@default:465',`  |


Follow the steps to set up Mailjet DSN,
1. Navigate to Configuration (/s/config/edit>)
2. Scroll to Email Settings
3. Update the following fields leaving rest default or empty,

| Field    | Value                                         |
|----------|-----------------------------------------------|
| Scheme   | `mautic+mailjet+smtp` or `mautic+mailjet+api` |
| Host     | `default`                                     |
| Port     | `465`                                         |
| User     | `<apiKey>`                                    |
| Password | `<secretKey>`                                 |

The `<apiKey>` and `<secretKey>` will be used for authentication purposes. Please visit the [Mailjet Guide][MailjetGuidePage]

On the Configuration page **Email DSN** should look like ![Email DSN](Docs/imgs/02%20-%20Email%20DSN.png "Email DSN")

### Mailjet

1. Create a [Mailjet account][MailjetSignup]
2. Then [retrieve your API and Secret keys][RetrieveKeys]. They will be used for authentication purposes.
3. Set up the webhook hook for event tracking from your account preferences, in the [Event Tracking section][EventTrackingSection]. The webhook URL should be `https://<your-domain.tld>/mailer/callback`.

## Usage

### Transport Modes

The plugin provides two transport modes:

**SMTP Relay** (`mautic+mailjet+smtp`) sends emails via the Mailjet SMTP server
on port 465 using TLS. This mode is particularly suitable if a local mail server
is already available to act as a relay.

**Email API** (`mautic+mailjet+api`) sends emails directly via the Mailjet REST
API and supports bulk sending with up to 50 recipients per request.
This mode is recommended for larger sending volumes.

### Webhook & Bounce Handling

The plugin automatically processes feedback from Mailjet via a dedicated
callback endpoint. The following events are handled in Mautic:

- **Hard Bounces & Blocked**: Contact is marked as bounced and excluded from
  further sending.
- **Spam Complaints**: Contact is marked as unsubscribed.
- **Unsubscribes**: Contact is marked as unsubscribed.

> **Note:** The webhook URL must be configured in the Mailjet account settings
> under Event Tracking and must be publicly accessible via HTTPS.
## Known Issues

## Troubleshooting
Make sure you have not only installed but also enabled the Plugin.
If things are still funny, please try
`php bin/console cache:clear`


## Change log
- https://github.com/Leuchtfeuer/mautic-MailjetAdapter-bundle/releases
## Future Ideas
- Plugin DEPRECTAED - No future improvements intended
## Sponsoring & Commercial Support
We are continuously improving our plugins. If you are requiring priority support or custom features, please contact us at mautic-plugins@leuchtfeuer.com.
## Get Involved
Feel free to open issues or submit pull requests on [GitHub](#). Follow the contribution guidelines in `CONTRIBUTING.md`.”
## Credits

## Author
Leuchtfeuer Digital Marketing GmbH
Please raise any issues in GitHub.
For all other things, please email mautic-plugins@Leuchtfeuer.com
## License
This plugin is licensed under the GPL v3 License.
## Resources / Further Readings



[MailjetGuidePage]: <https://dev.mailjet.com/email/guides/getting-started/>
[SendApiV31Home]: <https://dev.mailjet.com/email/guides/send-api-v31/>
[MailjetSignup]: <https://app.mailjet.com/signup>
[RetrieveKeys]: <https://app.mailjet.com/account/api_keys>
[EventTrackingSection]: <https://app.mailjet.com/account/triggers>
