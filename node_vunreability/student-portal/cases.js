export const cases = [
  {
    id: 'SR-01',
    area: 'Student records',
    title: 'A profile appears in the wrong session',
    evidence: 'A signed-in student opened an old bookmarked profile. The audit summary shows the returned profile and signed-in account belonged to different students.',
    investigation: 'What should the server establish before returning a student profile? Explain why a signed-in session alone is insufficient.',
    check: 'Compare the profile available to a student with the profiles available to a registrar. Record what each role is allowed to see.'
  },
  {
    id: 'SR-02',
    area: 'Registrar access',
    title: 'A student appears in the approval audit',
    evidence: 'An enrollment approval was attributed to a student account. The enrollment belonged to that student and was still waiting for registrar review.',
    investigation: 'Which part of the application should decide who can approve a request? What should happen if the account owns the request?',
    check: 'Follow one request from submission to approval. Record which account has the authority to complete each step.'
  },
  {
    id: 'SR-03',
    area: 'Sign-in',
    title: 'A credential field arrives in an unexpected shape',
    evidence: 'An old MongoDB sign-in trace classified one credential field as a structured value. The service continued to the account lookup.',
    investigation: 'What types belong at the sign-in boundary? Draw the flow from the HTTP request to account lookup and password verification.',
    check: 'Find where this portal checks formats and binds lookup values. Propose a test using an innocuous non-text value.'
  },
  {
    id: 'SR-04',
    area: 'Student records',
    title: 'A name becomes part of the page layout',
    evidence: 'The support team noticed that a record containing the text “Amina <Honours>” appeared as “Amina” in one view. The stored record still contained the full name.',
    investigation: 'What went wrong between storage and rendering? Where should the application handle text that resembles markup?',
    check: 'As registrar, save the name from the evidence to a demo record. Check the directory and profile for the full literal text.'
  },
  {
    id: 'SR-05',
    area: 'Sessions',
    title: 'A stale role appears in a browser backup',
    evidence: 'A support export showed a readable “registrar” role field in a browser cookie after the account had been reassigned to the student role.',
    investigation: 'Which source should be authoritative for roles? Compare what can happen with a browser-held role claim and a server-side session.',
    check: 'Use the browser’s cookie inspector during sign-in. Identify which information comes from the browser and which information is read on the server.'
  },
  {
    id: 'SR-06',
    area: 'Sign-in',
    title: 'Failed logins keep receiving fresh counters',
    evidence: 'An older incident report recorded repeated failed sign-ins from a single network client. The application assigned a fresh per-client counter several times.',
    investigation: 'Where should the server get the identity used for rate limiting? How does the answer change when a trusted reverse proxy is introduced?',
    check: 'Trace the login limit and the proxy setting in the current server. Describe a condition a test should verify for each setting.'
  },
  {
    id: 'SR-07',
    area: 'Course resources',
    title: 'A download appears outside the course catalog',
    evidence: 'A download audit listed a response associated with a file outside the approved resource directory. The file had no published catalog entry.',
    investigation: 'How should a public resource identifier be translated into a download? What outcome should an unknown identifier produce?',
    check: 'Download the published enrollment guide and verify that an ordinary unknown resource identifier returns a missing-resource page.'
  },
  {
    id: 'SR-08',
    area: 'Deployment',
    title: 'A developer backup appears in the web inventory',
    evidence: 'A deployment inventory listed a configuration backup in a browser-accessible directory. The configuration owner had classified it as private.',
    investigation: 'Which files belong in a public asset directory? What should the team do if a backup contained credentials?',
    check: 'Compare the portal’s asset directory with the full project tree and identify the exact directory served by the Node application.'
  },
  {
    id: 'SR-09',
    area: 'Enrollment rules',
    title: 'The transcript shows a negative credit total',
    evidence: 'An old request log reported an enrollment credit value different from the value listed in the course catalog. The transcript used the submitted value.',
    investigation: 'Who should supply course credits? What rule keeps an enrollment tied to a real catalog entry?',
    check: 'Trace an enrollment request to the catalog and identify the source of its credits. Record the expected behavior for an unknown course.'
  },
  {
    id: 'SR-10',
    area: 'Enrollment rules',
    title: 'One course is counted twice',
    evidence: 'Two ordinary submissions for the same student and course reached the old service nearly together. The transcript showed two entries for that pair.',
    investigation: 'What invariant must hold even when requests overlap? Where can it be enforced regardless of which request arrives first?',
    check: 'Run the duplicate-submission check in SR-10 of the project README. Use the enrollment schema to explain why the student still has one row for that course.'
  },
  {
    id: 'SR-11',
    area: 'Enrollment rules',
    title: 'A request begins in the approved state',
    evidence: 'An older audit row showed that a newly created enrollment was already approved. There was no recorded registrar decision.',
    investigation: 'Write down the permitted transitions and the actor allowed to perform each one. How can the server reject an out-of-order decision?',
    check: 'Compare the status shown immediately after submission with the status after registrar approval.'
  },
  {
    id: 'SR-12',
    area: 'Account recovery',
    title: 'A password reset is attributed to a different account',
    evidence: 'An archived helpdesk summary linked a completed password reset to an account other than the one that initiated the recovery flow.',
    investigation: 'Which identity should a reset grant be bound to? What should happen after it expires or is used once?',
    check: 'Write acceptance criteria for adding account recovery to the portal. Include a valid recovery and rejection after the first successful use.'
  }
];
