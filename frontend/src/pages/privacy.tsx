import { Link } from "wouter";
import { PublicLayout } from "@/components/layout/public-layout";

export default function PrivacyPage() {
  return (
    <PublicLayout>
      <div className="container mx-auto px-4 py-12 max-w-3xl prose prose-neutral dark:prose-invert">
        <h1>Privacy Policy</h1>
        <p className="text-sm text-muted-foreground">Last updated: 22 September 2026</p>

        <p>
          DriveIQ (&quot;we&quot;, &quot;us&quot;) operates a driving-school marketplace and school operations
          platform. This policy explains what personal data we collect, why, and your choices.
          It is written with India’s Digital Personal Data Protection (DPDP) Act principles in mind.
        </p>

        <h2>1. Data we collect</h2>
        <ul>
          <li><strong>Account data</strong> — name, email, password (hashed), role (learner, school, instructor, admin).</li>
          <li><strong>School &amp; training data</strong> — inquiries, learner profiles, schedules, attendance, progress, messages, invoices/payments records as needed to run the service.</li>
          <li><strong>Location (optional)</strong> — approximate coordinates only when you tap &quot;Use my location&quot; to find nearby schools. We do not continuously track your device.</li>
          <li><strong>Technical data</strong> — IP address (e.g. audit logs), browser storage for auth token and preferences.</li>
        </ul>

        <h2>2. How we use data</h2>
        <ul>
          <li>Provide search, booking, training, messaging, and payment features</li>
          <li>Authenticate users and enforce role-based access</li>
          <li>Improve reliability and prevent abuse (rate limits, audit logs)</li>
          <li>Respond to support and data-rights requests</li>
        </ul>

        <h2>3. Cookies &amp; local storage</h2>
        <p>DriveIQ primarily uses <strong>local storage</strong>, not advertising cookies:</p>
        <ul>
          <li><code>driveiq_auth_token</code> — session token after login (essential)</li>
          <li>Theme preference (essential / functional)</li>
          <li><code>driveiq_cookie_consent</code> — your consent choice</li>
          <li>Role portal data-use acknowledgment (per role)</li>
        </ul>
        <p>
          We do not currently load third-party advertising cookies. Optional analytics, if added,
          will only run after you choose &quot;Accept&quot; on the consent banner.
        </p>

        <h2>4. Location</h2>
        <p>
          Location access is <strong>opt-in</strong>. A purpose notice appears before the browser
          permission prompt. Coordinates are used to rank schools for that search only. We do not
          sell location data or build continuous location history.
        </p>

        <h2>5. Role portals</h2>
        <p>
          School, instructor, learner, and admin portals show a role-specific data-use notice on
          first entry. Processing is limited to purposes needed for that role (e.g. schedules for
          instructors, leads for schools).
        </p>

        <h2>6. Sharing</h2>
        <p>
          School operators see data needed to serve their learners. We do not sell personal data.
          Processors (hosting, email, payment gateways) may process data under contract. Card numbers
          are not stored by DriveIQ when online payments are enabled.
        </p>

        <h2>7. Retention</h2>
        <p>
          We keep data while needed for the service and for reasonable legal, tax, and dispute
          periods. Indicative schedules are maintained internally (see project DPDP notes). You may
          request deletion subject to lawful retention.
        </p>

        <h2>8. Your rights</h2>
        <ul>
          <li>Access / copy of your personal data</li>
          <li>Correction of inaccurate data</li>
          <li>Deletion (where not required to be retained)</li>
          <li>Withdrawal of consent for optional processing</li>
          <li>Nominate a person to exercise rights in case of incapacity or death</li>
        </ul>
        <p>
          Use <Link href="/privacy/data-request">Data rights request</Link>. We aim to respond within
          90 days after verifying identity.
        </p>

        <h2>9. Grievance redressal</h2>
        <p>
          <strong>Grievance contact:</strong> privacy@driveiq.in<br />
          <strong>Form:</strong> <Link href="/privacy/data-request">/privacy/data-request</Link> (request type: Other / grievance)<br />
          If unresolved, you may approach the Data Protection Board of India as provided under applicable law.
        </p>

        <h2>10. Security</h2>
        <p>
          Access controls, school-scoped isolation, hashed passwords, and HTTPS in production. Report
          concerns to privacy@driveiq.in.
        </p>

        <h2>11. Children</h2>
        <p>
          The service is aimed at adults arranging driving training. Contact us to remove a minor’s
          data submitted inappropriately.
        </p>

        <h2>12. Changes</h2>
        <p>
          We may update this policy; the &quot;Last updated&quot; date will change. Material changes may be
          highlighted in-product where appropriate.
        </p>

        <p className="text-sm text-muted-foreground not-prose mt-8">
          Also see <Link href="/terms" className="underline">Terms of Service</Link>.
        </p>
      </div>
    </PublicLayout>
  );
}
