<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/** The four app copy pages served at GET /api/content/{slug}. */
class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'about',
                'title' => 'About Open Referral',
                'intro' => 'A referral network that sends qualified buyers and sellers to licensed local agents.',
                'body_json' => [
                    ['heading' => 'What we do', 'body' => 'We qualify buyer and seller enquiries, then route each one to a licensed agent who actually covers that area. You see the full brief before you decide whether to take it.'],
                    ['heading' => 'Exclusive assignment', 'body' => 'Every lead is assigned to one agent at a time. You are never racing three other agents for the same client.'],
                    ['heading' => 'Accept or decline, always', 'body' => 'Nothing is forced on you. Accept a lead to add it to your pipeline, or decline it with a reason so we route the next one better.'],
                ],
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'intro' => 'How we handle agent and client information.',
                'body_json' => [
                    ['heading' => 'What we collect', 'body' => 'For agents: your name, email, phone, license details, brokerage, service areas, and the device token used to send you push notifications. For clients: the contact and property details they gave us when they enquired.'],
                    ['heading' => 'How we use it', 'body' => 'Agent details are used to match and assign leads and to contact you about your account. Client details are shared only with the agent the lead is assigned to.'],
                    ['heading' => 'What we never do', 'body' => 'We do not sell your personal information, and we do not share client contact details with anyone other than the assigned agent and our own staff.'],
                    ['heading' => 'Retention and deletion', 'body' => 'You can delete your account from the app at any time. We keep referral records needed for accounting and dispute resolution, with your profile removed from active use.'],
                    ['heading' => 'Contact', 'body' => 'Privacy questions can go to our support address, and we will respond within five business days.'],
                ],
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'intro' => 'The agreement covering your use of the Open Referral agent app.',
                'body_json' => [
                    ['heading' => 'Eligibility', 'body' => 'You must hold a valid real estate license in the states where you accept leads, and your account must be approved by our team before leads are assigned.'],
                    ['heading' => 'Lead handling', 'body' => 'Respond to assigned leads promptly. Accepting a lead means you will contact the client and work it in good faith. Repeated non-response may result in suspension.'],
                    ['heading' => 'Fees', 'body' => 'A referral fee applies per accepted lead, as shown on the lead before you accept. Fees become payable when you accept.'],
                    ['heading' => 'Client data', 'body' => 'Client information is confidential and provided solely for you to serve that client. Do not resell, redistribute, or add it to unrelated marketing lists.'],
                    ['heading' => 'Suspension and termination', 'body' => 'We may suspend or remove accounts for licensing lapses, client complaints, or breach of these terms. You may close your account at any time.'],
                ],
            ],
            [
                'slug' => 'help',
                'title' => 'Help & Support',
                'intro' => 'Answers to the questions we get most often.',
                'body_json' => [
                    ['heading' => 'Why is my account pending?', 'body' => 'Every new agent is reviewed by our team before leads can be assigned. Approval is usually same business day. You can fill in your profile and service areas while you wait.'],
                    ['heading' => 'How do I get more leads?', 'body' => 'Keep your service areas accurate and complete, respond quickly, and keep your acceptance rate healthy. Our team routes leads to agents who respond.'],
                    ['heading' => 'What happens when I decline a lead?', 'body' => 'The lead returns to our team with your reason attached, and we reassign it. Declining costs you nothing.'],
                    ['heading' => 'I am not getting notifications', 'body' => 'Check that push notifications are enabled both in the app settings and in your phone system settings, then sign out and back in to refresh your device token.'],
                    ['heading' => 'Still stuck?', 'body' => 'Contact our support team by email and include your agent name and any lead reference so we can help quickly.'],
                ],
            ],
        ];

        foreach ($pages as $page) {
            ContentPage::updateOrCreate(['slug' => $page['slug']], $page);
        }
    }
}
