<script setup lang="ts">
import LegalSection from '@/components/legal/LegalSection.vue';
import LegalShell from '@/components/legal/LegalShell.vue';
import { Head } from '@inertiajs/vue3';

const toc = [
    { id: 'acceptance', label: 'Acceptance of Terms' },
    { id: 'service', label: 'The Service' },
    { id: 'accounts', label: 'Accounts & Access' },
    { id: 'your-data', label: 'Your Data' },
    { id: 'acceptable-use', label: 'Acceptable Use' },
    { id: 'ai-features', label: 'AI Features' },
    { id: 'payment', label: 'Payment & Billing' },
    { id: 'ip', label: 'Intellectual Property' },
    { id: 'termination', label: 'Termination' },
    { id: 'liability', label: 'Limitation of Liability' },
    { id: 'indemnification', label: 'Indemnification' },
    { id: 'disputes', label: 'Dispute Resolution' },
    { id: 'changes', label: 'Changes to Terms' },
    { id: 'contact', label: 'Contact Us' },
];
</script>

<template>
    <Head title="Terms of Service" />
    <LegalShell
        title="Terms of Service"
        subtitle="Please read these terms carefully before using the RoutePilot platform."
        updated="Last updated: April 17, 2026 · Effective: April 17, 2026"
        :toc="toc"
    >
        <div class="rounded-xl border border-primary/25 bg-primary/10 p-4 text-sm text-foreground">
            These Terms of Service ("Terms") govern your access to and use of the <strong>RoutePilot</strong> platform operated by
            <strong>Punchlist Labs</strong>
            ("we," "our," or "us"). By creating an account or using the Service, you agree to be bound by these Terms.
        </div>

        <LegalSection :n="1" id="acceptance" title="Acceptance of Terms">
            <p>
                By accessing or using RoutePilot, you confirm that you are at least 18 years old and have the legal authority to enter into these
                Terms on behalf of yourself or the business entity you represent.
            </p>
            <p>If you do not agree with any part of these Terms, you must not use the Service.</p>
        </LegalSection>

        <LegalSection :n="2" id="service" title="The Service">
            <p>RoutePilot is a multi-tenant SaaS platform for pool and spa service companies. The Service includes:</p>
            <ul>
                <li>Route planning, scheduling, and optimization</li>
                <li>Water chemistry tracking, analysis, and treatment recommendations</li>
                <li>Customer and pool management</li>
                <li>Agent/technician mobile dashboards</li>
                <li>Customer-facing portal with service history and billing</li>
                <li>Customizable public landing pages per tenant</li>
                <li>AI-powered assistant for business and chemistry questions</li>
                <li>Notification and invoice systems</li>
                <li>Chemical inventory management</li>
            </ul>
            <p>We reserve the right to modify, suspend, or discontinue any part of the Service at any time with reasonable notice.</p>
        </LegalSection>

        <LegalSection :n="3" id="accounts" title="Accounts &amp; Access">
            <p>
                You are responsible for maintaining the confidentiality of your account credentials. You must not share your login with unauthorized
                individuals.
            </p>
            <p>
                As a tenant administrator, you may create accounts for agents (technicians) and grant portal access to customers. You are responsible
                for the activity of all users under your tenant account.
            </p>
            <p>You must provide accurate and complete information when creating your account and keep it up to date.</p>
        </LegalSection>

        <LegalSection :n="4" id="your-data" title="Your Data">
            <p>
                <strong>You own your data.</strong> All customer records, pool data, chemical readings, service reports, photos, and business content
                you create through RoutePilot belongs to you.
            </p>
            <p>
                You grant us a limited license to host, store, process, and display your data solely to provide the Service. This license ends when
                you delete your data or close your account.
            </p>
            <p>
                You are responsible for the legality and accuracy of the data you enter. You must have appropriate consent to store personal
                information about your customers (names, addresses, phone numbers, emails).
            </p>
            <p>We perform regular backups but recommend you maintain your own copies of critical business data.</p>
        </LegalSection>

        <LegalSection :n="5" id="acceptable-use" title="Acceptable Use">
            <p>You agree not to:</p>
            <ul>
                <li>Use the Service for any unlawful purpose</li>
                <li>Attempt to gain unauthorized access to other tenants' data</li>
                <li>Upload malicious code, viruses, or harmful content</li>
                <li>Abuse the AI Assistant with harmful, illegal, or exploitative prompts</li>
                <li>Use the platform to send unsolicited bulk communications (spam)</li>
                <li>Resell, sublicense, or redistribute the Service without written consent</li>
                <li>Scrape, crawl, or automate access to the platform beyond normal API usage</li>
                <li>Circumvent rate limits, authentication, or security controls</li>
            </ul>
            <p>Violation of these rules may result in immediate account suspension or termination.</p>
        </LegalSection>

        <LegalSection :n="6" id="ai-features" title="AI Features">
            <p>
                RoutePilot includes AI-powered features including a chat assistant and chemistry analysis recommendations. These features are provided
                for informational purposes and as decision-support tools.
            </p>
            <p>
                <strong>AI outputs are not guaranteed to be accurate.</strong> Chemistry recommendations, dosage calculations, and business advice
                generated by the AI should be verified by a qualified professional before acting on them. RoutePilot is not liable for damages
                resulting from reliance on AI-generated outputs.
            </p>
            <p>
                AI conversations may be processed by third-party AI providers (Anthropic, OpenAI). These providers operate under their own terms and
                privacy policies. We do not use your conversations to train AI models.
            </p>
        </LegalSection>

        <LegalSection :n="7" id="payment" title="Payment &amp; Billing">
            <p>
                RoutePilot may offer free and paid subscription tiers. If you subscribe to a paid plan, you agree to pay the applicable fees. Fees are
                billed in advance on a monthly or annual basis.
            </p>
            <p>
                RoutePilot does not directly process payments between you and your customers. The "Pay Now" links and invoice features in the customer
                portal direct customers to your own configured payment provider (PayPal, Stripe, Venmo, Zelle, or a custom URL). We are not a party to
                those transactions.
            </p>
            <p>
                We reserve the right to change pricing with 30 days' written notice. You may cancel your subscription at any time; cancellation takes
                effect at the end of the current billing period.
            </p>
        </LegalSection>

        <LegalSection :n="8" id="ip" title="Intellectual Property">
            <p>
                The RoutePilot platform, including its code, design, branding, documentation, and all related intellectual property, is owned by
                Punchlist Labs and protected by applicable copyright and trademark laws.
            </p>
            <p>
                Your business data, customer information, photos, and content remain your intellectual property. The landing pages and public sites
                generated by RoutePilot are branded with "Powered by RoutePilot" — you may not remove this attribution.
            </p>
            <p>You may not copy, modify, reverse-engineer, or create derivative works of the RoutePilot software.</p>
        </LegalSection>

        <LegalSection :n="9" id="termination" title="Termination">
            <p>
                You may close your account at any time by contacting us. We may suspend or terminate your account if you violate these Terms, fail to
                pay applicable fees, or engage in conduct that harms other users or the platform.
            </p>
            <p>
                Upon termination, your data is retained for 90 days and then permanently deleted. You may request an export of your data before
                termination.
            </p>
        </LegalSection>

        <LegalSection :n="10" id="liability" title="Limitation of Liability">
            <p>
                THE SERVICE IS PROVIDED "AS IS" WITHOUT WARRANTIES OF ANY KIND, EXPRESS OR IMPLIED. WE DO NOT WARRANT THAT THE SERVICE WILL BE
                UNINTERRUPTED, ERROR-FREE, OR SECURE.
            </p>
            <p>
                TO THE MAXIMUM EXTENT PERMITTED BY LAW, PUNCHLIST LABS SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR
                PUNITIVE DAMAGES, INCLUDING BUT NOT LIMITED TO LOSS OF PROFITS, DATA, OR BUSINESS OPPORTUNITIES.
            </p>
            <p>OUR TOTAL LIABILITY SHALL NOT EXCEED THE AMOUNT YOU PAID US IN THE TWELVE (12) MONTHS PRECEDING THE CLAIM.</p>
        </LegalSection>

        <LegalSection :n="11" id="indemnification" title="Indemnification">
            <p>
                You agree to indemnify and hold harmless Punchlist Labs, its officers, directors, employees, and agents from any claims, damages,
                losses, liabilities, and expenses (including attorneys' fees) arising from your use of the Service, your violation of these Terms, or
                your violation of any third-party rights.
            </p>
        </LegalSection>

        <LegalSection :n="12" id="disputes" title="Dispute Resolution">
            <p>These Terms are governed by the laws of the State of Georgia, United States, without regard to conflict of law principles.</p>
            <p>
                Any disputes arising from these Terms or the Service shall first be attempted to be resolved through good-faith negotiation. If
                negotiation fails, disputes shall be resolved through binding arbitration in accordance with the rules of the American Arbitration
                Association.
            </p>
        </LegalSection>

        <LegalSection :n="13" id="changes" title="Changes to Terms">
            <p>
                We may update these Terms from time to time. When we make material changes, we will notify you by email or through a prominent notice
                on the platform at least 30 days before the changes take effect. Your continued use of the Service after the effective date
                constitutes acceptance of the updated Terms.
            </p>
        </LegalSection>

        <LegalSection :n="14" id="contact" title="Contact Us">
            <p>If you have questions about these Terms, contact us at:</p>
            <p>
                <strong>Punchlist Labs</strong><br />
                Email: <a href="mailto:legal@punchlistlabs.com">legal@punchlistlabs.com</a><br />
                Web: <a href="https://punchlistlabs.com" target="_blank" rel="noopener">punchlistlabs.com</a>
            </p>
        </LegalSection>
    </LegalShell>
</template>
