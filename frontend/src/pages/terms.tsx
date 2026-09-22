import { Link } from "wouter";
import { PublicLayout } from "@/components/layout/public-layout";

export default function TermsPage() {
  return (
    <PublicLayout>
      <div className="container mx-auto px-4 py-12 max-w-3xl prose prose-neutral dark:prose-invert">
        <h1>Terms of Service</h1>
        <p className="text-muted-foreground text-sm">Last updated: 22 September 2026</p>

        <p>
          These Terms govern use of the DriveIQ website and applications (&quot;Service&quot;). By creating an
          account or using the Service you agree to these Terms and our{" "}
          <Link href="/privacy">Privacy Policy</Link>.
        </p>

        <h2>1. What DriveIQ provides</h2>
        <p>
          DriveIQ is a marketplace and operations platform connecting learners with driving schools
          and supporting school workflows (leads, schedules, payments records, messaging, analytics).
          DriveIQ is not a government portal and does not issue licenses.
        </p>

        <h2>2. Accounts & roles</h2>
        <ul>
          <li>You must provide accurate registration information.</li>
          <li>You are responsible for activity under your account and for keeping credentials safe.</li>
          <li>
            Roles (learner, school, instructor, platform admin) determine what you can access. Do not
            attempt to access another school’s or user’s data.
          </li>
        </ul>

        <h2>3. Schools and listings</h2>
        <p>
          School profiles, packages, prices, and availability are provided by schools. DriveIQ does
          not guarantee training outcomes, RTO success, or continuous availability of any school.
        </p>

        <h2>4. Bookings, training & payments</h2>
        <p>
          Training contracts are primarily between the learner and the school. Platform payment
          features may record cash/manual payments or, when enabled, route online payments via a
          third-party gateway. Chargebacks and refunds follow school and gateway rules.
        </p>

        <h2>5. Acceptable use</h2>
        <p>You agree not to:</p>
        <ul>
          <li>Abuse, scrape, or overload the Service</li>
          <li>Post false reviews or spam inquiries</li>
          <li>Upload unlawful or infringing content</li>
          <li>Interfere with security or multi-tenant isolation</li>
        </ul>

        <h2>6. Location features</h2>
        <p>
          Optional &quot;near me&quot; search uses device location with your permission solely to rank nearby
          schools. You may use locality filters instead.
        </p>

        <h2>7. Intellectual property</h2>
        <p>
          DriveIQ branding, software, and original content remain our property. School content remains
          the school’s responsibility.
        </p>

        <h2>8. Disclaimers</h2>
        <p>
          The Service is provided &quot;as is&quot;. Driving rules and government process information (if shown)
          may change; always verify on official portals. We disclaim warranties to the fullest extent
          permitted by law.
        </p>

        <h2>9. Limitation of liability</h2>
        <p>
          To the extent allowed by law, DriveIQ is not liable for indirect or consequential damages,
          or for disputes solely between learners and schools. Our aggregate liability relating to
          the Service is limited to fees you paid to DriveIQ (if any) in the three months before the
          claim.
        </p>

        <h2>10. Termination</h2>
        <p>
          We may suspend or terminate accounts that violate these Terms or pose security risk. You may
          stop using the Service and request account deletion via our{" "}
          <Link href="/privacy/data-request">data rights form</Link>.
        </p>

        <h2>11. Governing law</h2>
        <p>
          These Terms are governed by the laws of India. Courts in Pune, Maharashtra shall have
          exclusive jurisdiction, subject to mandatory consumer protections where applicable.
        </p>

        <h2>12. Contact</h2>
        <p>
          Questions: <Link href="/contact">Contact</Link>.
        </p>
      </div>
    </PublicLayout>
  );
}
