<?php

namespace App\Modules\Cms\Services;

use App\Modules\Cms\Models\Page;

/**
 * Starter texts for the pages every SaaS site needs (privacy, terms, cookies, refunds). They are a sensible starting point written
 * in plain language, not legal advice: the owner must read them, fill in the gaps and have them checked for the countries they sell in.
 * Pages are created unpublished so nothing goes live by accident.
 */
class LegalTemplates
{
    /** @return array<string, array{title: string, body: string}> */
    public function all(string $site, string $email, string $url): array
    {
        $fill = fn (string $text) => strtr($text, ['{site}' => $site, '{email}' => $email ?: '[your contact e-mail]', '{url}' => $url]);

        return [
            'privacy-policy' => ['title' => 'Privacy Policy', 'body' => $fill(<<<'MD'
## Who we are
{site} ({url}) is run by [your company name, address, registration number]. You can reach us at {email}.

## What we collect
- **Account data:** name, e-mail address, password (stored hashed), restaurant details, staff accounts.
- **Billing data:** plan, invoices, billing address and tax number. Card details are handled by our payment providers and never stored by us.
- **Usage data:** pages visited, device and browser type, IP address, and security logs.
- **Content you add:** menus, photos, orders, reservations and customer lists of your restaurant.

## Why we use it
To run the service, bill you, keep it secure, answer support requests, send service e-mails, and improve the product. Marketing e-mails are sent only if you agree and you can stop them at any time.

## Who we share it with
Hosting, e-mail and SMS delivery, payment, analytics and AI providers that work for us ([list them]). We do not sell personal data.

## Your restaurant's customers
For the guests of your restaurant, you decide why their data is collected and we process it for you. A data processing agreement is available on request.

## How long we keep it
While your account is active and as long as the law requires for invoices and accounting, then it is deleted or anonymised.

## Your rights
Depending on where you live (for example under the GDPR or the CCPA) you can ask to see, correct, export or delete your data, object to processing, and complain to your data protection authority. Write to {email}.

## Cookies
See our Cookie Policy.

## International transfers
Where data leaves your country we use safeguards such as standard contractual clauses.

## Changes
We will post changes on this page and tell you about important ones.

*Last updated: [date]*
MD)],
            'terms-of-service' => ['title' => 'Terms of Service', 'body' => $fill(<<<'MD'
## Agreement
By creating an account on {site} ({url}) you agree to these terms. If you use the service for a company, you confirm you may bind it.

## The service
{site} lets restaurants publish digital menus, take orders and manage their business. We may improve or change features; we will tell you about changes that matter.

## Your account
Keep your password safe. You are responsible for what happens under your account and for the staff you invite.

## Plans, payment and renewal
Paid plans are billed in advance for the period you choose and renew automatically unless cancelled before the renewal date. Prices may change with notice before the next period. Taxes are added where required.

## Cancellation and refunds
You can cancel at any time; the service stays active until the end of the paid period. [Describe your refund rules, or link to the Refund Policy.]

## Your content
You own your content. You allow us to host and display it to run the service. You promise that you have the right to use it (photos, texts, prices, allergen information) and that it is accurate; allergen and nutrition information is your responsibility.

## Acceptable use
No illegal content, no attempts to break or overload the service, no sending spam, no reselling without our written permission.

## Availability
We work hard to keep the service running but do not promise it will be uninterrupted or error free.

## Liability
To the extent the law allows, we are not liable for indirect losses or lost profit, and our total liability is limited to what you paid us in the last [12] months.

## Ending the agreement
We may suspend or close accounts that break these terms. You can export your data before closing.

## Law and disputes
These terms are governed by the laws of [country]. Disputes go to the courts of [city], unless the law of your country says otherwise.

## Contact
{email}

*Last updated: [date]*
MD)],
            'cookie-policy' => ['title' => 'Cookie Policy', 'body' => $fill(<<<'MD'
## What cookies are
Small files a website stores in your browser. They remember your choices and help us understand how the site is used.

## Cookies we use
- **Necessary:** keep you signed in, protect forms against attacks, remember your language and your cookie choice. These do not need consent.
- **Analytics:** with your consent, tools such as Google Analytics or Google Tag Manager tell us which pages are used so we can improve the site.
- **Marketing:** with your consent, advertising pixels may measure the results of campaigns.

## Your choice
When you first visit {site} you can accept or decline non-essential cookies. You can change your mind by clearing the cookies of this site in your browser, after which the question appears again.

## Contact
Questions: {email}

*Last updated: [date]*
MD)],
            'refund-policy' => ['title' => 'Refund Policy', 'body' => $fill(<<<'MD'
## Subscriptions
[Choose and write your rule. For example:] If you are not satisfied, write to {email} within [14] days of your first payment and we will refund it in full. Later renewals are not refundable, but you can cancel at any time to stop the next one.

## Store items (themes, premium features)
[For example:] Items you rent or buy in the store are delivered at once, so they are refunded only if they do not work as described and we cannot fix the problem.

## How refunds are paid
To the original payment method, usually within [5–10] business days.

## Contact
{email}

*Last updated: [date]*
MD)],
        ];
    }

    /**
     * Creates the pages that do not exist yet, unpublished, in the given language.
     *
     * @return int how many were created
     */
    public function install(string $locale, string $site, string $email, string $url): int
    {
        $created = 0;
        $sort = (int) Page::max('sort');

        foreach ($this->all($site, $email, $url) as $slug => $page) {
            if (Page::where('slug', $slug)->where('locale', $locale)->exists()) {
                continue;
            }

            Page::create(['slug' => $slug, 'locale' => $locale, 'title' => $page['title'], 'body' => $page['body'], 'is_published' => false, 'in_footer' => true, 'sort' => ++$sort]);
            $created++;
        }

        return $created;
    }
}
