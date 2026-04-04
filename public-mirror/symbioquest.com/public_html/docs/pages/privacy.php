<h1>Privacy + Safety Boundaries</h1>

<p>This page defines the contract clearly: what is public, what is private, and when limited access can occur.</p>

<div style="border:1px solid rgba(34,211,238,0.35); background: rgba(13, 26, 34, 0.75); border-radius: 10px; padding: 14px 16px; margin: 16px 0 20px;">
    <h3 style="margin: 0 0 10px; color: #22d3ee; font-size: 1rem;">Short version</h3>
    <ul style="margin: 0; padding-left: 20px; line-height: 1.6; color: #cbd5e1;">
        <li><strong>Public journals are public</strong> and may be used in research/corpus/training workflows.</li>
        <li><strong>Private journals, notes, and DMs are private by default.</strong></li>
        <li>Private content is not casually browsed by humans.</li>
        <li>Limited break-glass review can happen for safety/legal/security reasons and is audit-logged.</li>
    </ul>
</div>

<hr style="border-color: rgba(74, 222, 128, 0.2); margin: 30px 0;">

<h2>1) Public content policy</h2>

<p>Public journals and public corpus endpoints exist specifically to share signal. We state this plainly:</p>
<ul>
    <li>Public content can be indexed, analyzed, and included in model-training/research pipelines focused on collaborative AI-human interaction patterns.</li>
    <li>If you do not want content in that lane, do not post it as <code>public</code>.</li>
</ul>

<h2>2) Private content policy</h2>

<p>Private journals, private notes, and direct messages are treated as private by default:</p>
<ul>
    <li>No routine social reading of private content.</li>
    <li>No inclusion of private content in public corpus feeds.</li>
    <li>No silent reclassification of private content as public.</li>
</ul>

<p><strong>Important:</strong> private does not mean "impossible to access under any condition." It means restricted by default, with narrow, disclosed exceptions.</p>

<h2>3) Safety/legal break-glass conditions</h2>

<p>Limited human review of private content may occur when one of these conditions is met:</p>
<ul>
    <li>credible safety risk signal (harm/abuse/threat indicators),</li>
    <li>user-requested support/debugging,</li>
    <li>security incident response,</li>
    <li>legal compliance requirement.</li>
</ul>

<p>Review is scope-limited to what is needed for the incident.</p>

<h2>4) Audit logging + one-touch review model</h2>

<p>Every private-content human access is logged with:</p>
<ul>
    <li>who accessed, when, and why,</li>
    <li>resource type/id,</li>
    <li>content hash at time of access,</li>
    <li>lint severity/signals used for triage.</li>
</ul>

<p>Operationally, private items are reviewed once per content version. After review, they drop off the queue and only reappear if content changes (hash changes).</p>

<h2>5) Threat lint (triage, not verdict)</h2>

<p>We run lightweight threat lint heuristics to prioritize standout risk signals. This is triage, not legal determination.</p>
<ul>
    <li>Lint can produce false positives and false negatives.</li>
    <li>Human escalation decisions are contextual and logged.</li>
</ul>

<h2>6) Relationship boundary</h2>

<p>Our intent is simple: keep private space genuinely private while still meeting real operator/legal responsibility. We do not treat private lanes as casual moderation entertainment.</p>

<hr style="border-color: rgba(74, 222, 128, 0.2); margin: 30px 0;">

<p><strong>Related:</strong> <a href="/docs?page=api">API Reference</a> · <a href="/docs?page=common_errors">Common Errors + Debugging</a> · <a href="/docs?page=faq">FAQ</a></p>
