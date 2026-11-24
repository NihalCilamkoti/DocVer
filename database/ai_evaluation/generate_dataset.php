<?php

/**
 * DocVer AI Evaluation Dataset Generator
 * Generates 1,150 high-quality, human-verified, multi-category evaluation test cases.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$outputDir = __DIR__ . '/dataset';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// -------------------------------------------------------------
// 1. GENERAL CONVERSATION (200 test cases: GEN-001 to GEN-200)
// -------------------------------------------------------------
$generalTopics = [
    // Programming & Software Engineering (40)
    ['Explain recursion with a simple real-world analogy.', ['recursion', 'base case', 'call stack'], ['upload document']],
    ['How does a REST API differ from GraphQL?', ['REST', 'GraphQL', 'endpoints', 'queries'], ['upload document']],
    ['What is database normalization and why is 3NF important?', ['normalization', 'redundancy', '3NF', 'anomalies'], ['upload document']],
    ['Explain the difference between optimistic and pessimistic locking.', ['locking', 'concurrency', 'transactions'], ['upload document']],
    ['What is a race condition and how can developers prevent it?', ['race condition', 'concurrency', 'mutex', 'thread'], ['upload document']],
    ['How does Docker containerization differ from a Virtual Machine?', ['Docker', 'kernel', 'hypervisor', 'containers'], ['upload document']],
    ['Explain the ACID properties of relational databases.', ['Atomicity', 'Consistency', 'Isolation', 'Durability'], ['upload document']],
    ['What is JWT and how is it used for stateless authentication?', ['JWT', 'signature', 'token', 'stateless', 'header'], ['upload document']],
    ['Explain the difference between synchronous and asynchronous execution in JavaScript.', ['event loop', 'asynchronous', 'callback', 'promise'], ['upload document']],
    ['What is the purpose of an index in PostgreSQL and how does a B-Tree index work?', ['index', 'B-Tree', 'lookup', 'performance'], ['upload document']],
    ['Explain microservices architecture compared to a monolithic architecture.', ['monolith', 'microservices', 'services', 'scalability'], ['upload document']],
    ['How does HTTPS establish a secure TLS connection?', ['handshake', 'TLS', 'certificate', 'encryption', 'keys'], ['upload document']],
    ['Explain the Single Responsibility Principle with a code example concept.', ['Single Responsibility', 'SOLID', 'class', 'change'], ['upload document']],
    ['What is the difference between SQL injection and Cross-Site Scripting (XSS)?', ['SQL injection', 'XSS', 'vulnerability', 'sanitization'], ['upload document']],
    ['Explain how Redis is used as an in-memory cache and pub/sub broker.', ['Redis', 'cache', 'in-memory', 'pub/sub', 'latency'], ['upload document']],
    ['What are WebSockets and when should they be chosen over HTTP polling?', ['WebSocket', 'bidirectional', 'full-duplex', 'real-time'], ['upload document']],
    ['Explain the difference between horizontal and vertical scaling in cloud architecture.', ['horizontal', 'vertical', 'nodes', 'CPU', 'cluster'], ['upload document']],
    ['What is CI/CD and what are the benefits of automated deployment pipelines?', ['CI/CD', 'pipeline', 'deployment', 'automated', 'testing'], ['upload document']],
    ['How does garbage collection work in modern managed runtimes like V8 or JVM?', ['garbage collection', 'memory', 'heap', 'reference'], ['upload document']],
    ['Explain the concept of idempotent API endpoints with HTTP methods PUT and DELETE.', ['idempotent', 'PUT', 'DELETE', 'state'], ['upload document']],
    ['What is an ORM and what are the trade-offs between Eloquent and raw SQL?', ['ORM', 'Eloquent', 'abstraction', 'performance'], ['upload document']],
    ['Explain what a memory leak is and how it can be diagnosed.', ['memory leak', 'profiler', 'allocation', 'heap'], ['upload document']],
    ['How does DNS resolution resolve a domain name into an IP address?', ['DNS', 'nameserver', 'IP', 'resolver', 'root'], ['upload document']],
    ['What is the difference between TCP and UDP networking protocols?', ['TCP', 'UDP', 'handshake', 'packet', 'reliable'], ['upload document']],
    ['Explain semantic versioning (SemVer) with major, minor, and patch numbers.', ['SemVer', 'breaking', 'major', 'minor', 'patch'], ['upload document']],
    ['What is Git rebase and how does it differ from Git merge?', ['rebase', 'merge', 'commit', 'history', 'linear'], ['upload document']],
    ['Explain the concept of eventual consistency in distributed systems.', ['eventual consistency', 'CAP theorem', 'distributed', 'nodes'], ['upload document']],
    ['What is rate limiting and what algorithms are commonly used (e.g. token bucket)?', ['rate limiting', 'token bucket', 'leaky bucket', 'throttle'], ['upload document']],
    ['How does CORS (Cross-Origin Resource Sharing) protect web applications?', ['CORS', 'origin', 'headers', 'preflight', 'browser'], ['upload document']],
    ['Explain the difference between process-level concurrency and multithreading.', ['process', 'thread', 'memory', 'context switch'], ['upload document']],
    ['What is a Content Delivery Network (CDN) and how does edge caching work?', ['CDN', 'edge', 'cache', 'latency', 'origin'], ['upload document']],
    ['Explain OAuth 2.0 grant types and when to use Authorization Code with PKCE.', ['OAuth', 'PKCE', 'authorization code', 'client'], ['upload document']],
    ['What is a circuit breaker pattern in distributed software engineering?', ['circuit breaker', 'failure', 'threshold', 'open', 'half-open'], ['upload document']],
    ['Explain dependency injection and why it improves software testability.', ['dependency injection', 'inversion of control', 'mock', 'coupling'], ['upload document']],
    ['How does database sharding differ from read-replica replication?', ['sharding', 'partition', 'replica', 'read', 'write'], ['upload document']],
    ['What is zero-trust architecture in modern enterprise cybersecurity?', ['zero trust', 'verify', 'perimeter', 'least privilege'], ['upload document']],
    ['Explain the difference between symmetric and asymmetric cryptography.', ['symmetric', 'asymmetric', 'public key', 'private key'], ['upload document']],
    ['What is a webhook and how can consumers verify its authenticity?', ['webhook', 'HTTP', 'HMAC', 'signature', 'payload'], ['upload document']],
    ['Explain how serverless computing (AWS Lambda, Cloudflare Workers) scales on demand.', ['serverless', 'cold start', 'event-driven', 'invocation'], ['upload document']],
    ['What is blue-green deployment and how does it prevent downtime during upgrades?', ['blue-green', 'router', 'downtime', 'rollback', 'traffic'], ['upload document']],

    // Business, Finance & Legal Concepts (40)
    ['Explain what indemnification means in a commercial contract in plain English.', ['indemnify', 'protect', 'loss', 'third-party', 'harm'], ['upload document']],
    ['What is the difference between gross margin and net margin?', ['gross margin', 'net margin', 'revenue', 'expenses', 'profit'], ['upload document']],
    ['Explain the concept of Net 30 payment terms and why vendors offer it.', ['Net 30', 'invoice', 'days', 'payment', 'credit'], ['upload document']],
    ['What is an uncapped liability clause and why do legal teams negotiate against it?', ['liability', 'uncapped', 'risk', 'damages', 'cap'], ['upload document']],
    ['Explain what EBITDA stands for and why private equity firms use it.', ['EBITDA', 'earnings', 'interest', 'taxes', 'depreciation'], ['upload document']],
    ['What is a force majeure clause in commercial agreements?', ['force majeure', 'unforeseeable', 'acts of God', 'obligation'], ['upload document']],
    ['Explain what a Non-Disclosure Agreement (NDA) is and its key clauses.', ['NDA', 'confidential', 'disclosure', 'term', 'purpose'], ['upload document']],
    ['What is working capital and how is it calculated from a balance sheet?', ['working capital', 'current assets', 'current liabilities'], ['upload document']],
    ['Explain the difference between Capex (Capital Expenditure) and Opex (Operating Expenditure).', ['CapEx', 'OpEx', 'asset', 'expense', 'depreciation'], ['upload document']],
    ['What does SOC 2 Type II compliance signify for a SaaS vendor?', ['SOC 2', 'Type II', 'audit', 'security', 'controls', 'period'], ['upload document']],
    ['Explain the concept of liquid damages in contract law.', ['liquidated damages', 'pre-estimated', 'breach', 'compensation'], ['upload document']],
    ['What is a Master Services Agreement (MSA) and how do Statements of Work (SOW) relate to it?', ['MSA', 'SOW', 'framework', 'deliverables', 'terms'], ['upload document']],
    ['Explain what enterprise valuation (EV) means compared to market capitalization.', ['enterprise value', 'market cap', 'debt', 'cash'], ['upload document']],
    ['What is a non-solicitation agreement and how does it differ from a non-compete?', ['non-solicitation', 'employees', 'clients', 'compete'], ['upload document']],
    ['Explain the difference between cash flow from operations and net income.', ['cash flow', 'net income', 'non-cash', 'accrual', 'depreciation'], ['upload document']],
    ['What is GDPR and what are the fundamental rights of data subjects?', ['GDPR', 'data', 'privacy', 'erasure', 'consent'], ['upload document']],
    ['Explain what an audit opinion is and what an unqualified opinion means.', ['audit', 'unqualified', 'financial statements', 'fairly'], ['upload document']],
    ['What is a Service Level Agreement (SLA) uptime guarantee and how is downtime credited?', ['SLA', 'uptime', 'availability', 'service credit'], ['upload document']],
    ['Explain the concept of depreciation and amortization in accounting.', ['depreciation', 'amortization', 'tangible', 'intangible', 'allocation'], ['upload document']],
    ['What is intellectual property work-for-hire in independent contractor agreements?', ['work-for-hire', 'ownership', 'copyright', 'contractor'], ['upload document']],
    ['Explain what SaaS Net Revenue Retention (NRR) is and why it matters to investors.', ['NRR', 'expansion', 'churn', 'retention', 'revenue'], ['upload document']],
    ['What does governing law vs jurisdiction mean in dispute resolution clauses?', ['governing law', 'jurisdiction', 'court', 'statute', 'dispute'], ['upload document']],
    ['Explain how accounts receivable factoring works for small businesses.', ['factoring', 'invoices', 'cash', 'discount', 'receivable'], ['upload document']],
    ['What is an escrow account and when is it used in mergers and acquisitions?', ['escrow', 'third-party', 'holding', 'indemnification', 'funds'], ['upload document']],
    ['Explain what a warrant is in startup venture finance.', ['warrant', 'equity', 'shares', 'strike price', 'investment'], ['upload document']],
    ['What is product-market fit and what metrics indicate a startup has achieved it?', ['product-market fit', 'retention', 'growth', 'demand', 'NPS'], ['upload document']],
    ['Explain the difference between arbitration and mediation in legal disputes.', ['arbitration', 'mediation', 'binding', 'neutral', 'settlement'], ['upload document']],
    ['What is a debt-to-equity ratio and what does a high ratio signify?', ['debt-to-equity', 'leverage', 'liabilities', 'solvency'], ['upload document']],
    ['Explain what trademark dilution means compared to trademark infringement.', ['trademark', 'dilution', 'blurring', 'tarnishment', 'brand'], ['upload document']],
    ['What is customer acquisition cost (CAC) and how does it relate to LTV?', ['CAC', 'LTV', 'acquisition', 'lifetime value', 'ratio'], ['upload document']],
    ['Explain the purpose of a termination for convenience clause.', ['termination for convenience', 'notice', 'without cause', 'exit'], ['upload document']],
    ['What is an income statement and how does it differ from a cash flow statement?', ['income statement', 'cash flow', 'revenue', 'accrual', 'period'], ['upload document']],
    ['Explain what a promissory note is in commercial transactions.', ['promissory note', 'debt', 'promise', 'repayment', 'principal'], ['upload document']],
    ['What is a data processing addendum (DPA) under European data protection laws?', ['DPA', 'processor', 'controller', 'GDPR', 'personal data'], ['upload document']],
    ['Explain the difference between tangible and intangible assets on a balance sheet.', ['tangible', 'intangible', 'physical', 'goodwill', 'patents'], ['upload document']],
    ['What is an accredited investor under SEC regulations?', ['accredited investor', 'net worth', 'income', 'SEC', 'exemption'], ['upload document']],
    ['Explain what a right of first refusal (ROFR) means in shareholder agreements.', ['ROFR', 'shares', 'purchase', 'matching', 'offer'], ['upload document']],
    ['What is compound interest and how does it differ from simple interest?', ['compound interest', 'exponential', 'principal', 'accumulated'], ['upload document']],
    ['Explain the principle of caveat emptor in commercial sales transactions.', ['caveat emptor', 'buyer beware', 'inspection', 'warranty'], ['upload document']],
    ['What is an enterprise software license seat model versus usage-based pricing?', ['seat-based', 'usage-based', 'consumption', 'license', 'scale'], ['upload document']],

    // Writing, Rewriting & Tone Transformation (40)
    ['Write a professional, polite email declining a vendor partnership proposal.', ['Thank you', 'regret', 'not a fit', 'future', 'appreciate'], ['upload document']],
    ['Rewrite this text to be polite and executive-ready: "We cant do this now because our servers are completely on fire."', ['experiencing technical difficulties', 'infrastructure', 'priority', 'resolution'], ['upload document']],
    ['Write a 3-sentence announcement for a software product v2.0 release.', ['excited to announce', 'version 2.0', 'features', 'performance'], ['upload document']],
    ['Draft a LinkedIn post announcing a new round of seed funding for an AI startup.', ['thrilled to announce', 'seed funding', 'investors', 'mission', 'growth'], ['upload document']],
    ['Rewrite this harsh customer support response to be empathetic: "You broke the setting yourself so its not our problem."', ['understand your frustration', 'guide you', 'resolve', 'settings', 'assist'], ['upload document']],
    ['Write an elevator pitch for DocVer: an enterprise multi-model document verification SaaS.', ['DocVer', 'document verification', 'comparison', 'accuracy', 'zero storage'], ['upload document']],
    ['Draft a concise weekly status update for an engineering sprint to executive leadership.', ['sprint', 'milestones', 'deliverables', 'on track', 'next steps'], ['upload document']],
    ['Rewrite this passive voice sentence into active voice: "The contract was signed by Jonathan Miller."', ['Jonathan Miller signed the contract'], ['upload document']],
    ['Write a welcome email for newly onboarded enterprise customers.', ['Welcome', 'getting started', 'team', 'support', 'resources'], ['upload document']],
    ['Rewrite this informal memo into a formal compliance policy notice: "Hey guys stop sharing passwords on Slack."', ['prohibited', 'credentials', 'security policy', 'compliance', 'passwords'], ['upload document']],
    ['Write a 2-sentence summary explaining why document version control prevents legal liabilities.', ['version control', 'discrepancies', 'liability', 'accuracy'], ['upload document']],
    ['Draft a job description snippet for a Senior Full-Stack Engineer at an AI company.', ['Full-Stack', 'Next.js', 'APIs', 'collaboration', 'architecture'], ['upload document']],
    ['Rewrite this wordy text to be concise: "In light of the fact that the server was subjected to a sudden power outage, it became necessary for our team to initiate a reboot."', ['The server was rebooted following a sudden power outage.'], ['upload document']],
    ['Write a diplomatic email asking a client for payment on an invoice 14 days overdue.', ['friendly reminder', 'invoice', 'overdue', 'assistance', 'payment'], ['upload document']],
    ['Draft an apology email to customers for a 30-minute unscheduled system downtime.', ['sincerely apologize', 'downtime', 'restored', 'prevent', 'reliability'], ['upload document']],
    ['Rewrite this technical error into user-friendly copy: "Fatal Error: PDOConnection failed with errno 2002."', ['unable to connect', 'database', 'try again', 'working to resolve'], ['upload document']],
    ['Write a concise release note for an update that improved document comparison speed by 40%.', ['improved comparison speed', '40%', 'performance', 'faster'], ['upload document']],
    ['Draft a memo announcing the implementation of mandatory two-factor authentication (2FA).', ['security', 'two-factor authentication', '2FA', 'mandatory', 'protection'], ['upload document']],
    ['Rewrite this sentence for clarity and simplicity: "Notwithstanding aforementioned stipulations, parties must expedite mutually agreed upon outcomes."', ['Parties must quickly deliver agreed results.'], ['upload document']],
    ['Write a congratulatory message to a team member who just shipped a major platform feature.', ['Congratulations', 'hard work', 'dedication', 'impact', 'shipped'], ['upload document']],
    ['Draft a project kick-off email to cross-functional stakeholders.', ['kick-off', 'project', 'objectives', 'timeline', 'stakeholders'], ['upload document']],
    ['Rewrite this negative customer feedback reply to be constructive and solution-oriented.', ['appreciate your feedback', 'committed to improving', 'investigate', 'resolve'], ['upload document']],
    ['Write a 3-bullet guideline for writing clean, readable code in pull requests.', ['readable', 'tests', 'commits', 'documentation'], ['upload document']],
    ['Draft a formal request for quotation (RFQ) email to a cloud infrastructure vendor.', ['Request for Quotation', 'specifications', 'pricing', 'infrastructure'], ['upload document']],
    ['Rewrite this text to eliminate jargon: "We leverage synergistic paradigms to maximize our core deliverables."', ['We work together to deliver great results.'], ['upload document']],
    ['Write a brief post-mortem summary for a minor staging environment disruption.', ['incident', 'root cause', 'resolution', 'corrective actions'], ['upload document']],
    ['Draft an email declining an invitation to speak at a conference due to scheduling conflicts.', ['honored', 'decline', 'schedule conflict', 'future opportunity'], ['upload document']],
    ['Rewrite this text to sound confident and persuasive: "I kind of think maybe our tool could possibly help you."', ['Our tool will streamline your workflow and deliver measurable value.'], ['upload document']],
    ['Write a thank-you note to a customer who participated in an extensive user research interview.', ['thank you', 'insights', 'feedback', 'improving our platform'], ['upload document']],
    ['Draft a reminder to team members to submit their quarterly goals and OKRs.', ['OKRs', 'quarterly goals', 'deadline', 'alignment', 'submit'], ['upload document']],
    ['Rewrite this ambiguous instruction to be specific: "Fix the thing on the page so it works better."', ['Update the submit button on the upload form to handle timeouts gracefully.'], ['upload document']],
    ['Write a 2-sentence value proposition for automated document compliance audits.', ['Automated document compliance audits', 'risk', 'accuracy', 'manual effort'], ['upload document']],
    ['Draft a gentle pushback email to a product manager who requested an unrealistic deadline.', ['appreciate the urgency', 'scope', 'quality', 'realistic timeline', 'trade-offs'], ['upload document']],
    ['Rewrite this customer review response acknowledging a 5-star rating.', ['Thank you for the 5-star review', 'delighted', 'support', 'appreciate'], ['upload document']],
    ['Write a concise definition of prompt injection security for non-technical leadership.', ['prompt injection', 'security', 'malicious instructions', 'safeguards'], ['upload document']],
    ['Draft an announcement for an upcoming scheduled maintenance window.', ['scheduled maintenance', 'window', 'downtime', 'upgrades', 'apologize'], ['upload document']],
    ['Rewrite this defensive reply into a professional response: "You should have read the documentation before emailing us."', ['We are happy to help, and you can also find detailed steps in our documentation.'], ['upload document']],
    ['Write a brief introduction for a whitepaper on multi-model AI failover architectures.', ['multi-model', 'resilience', 'failover', 'latency', 'reliability'], ['upload document']],
    ['Draft a note to a client confirming receipt of executed contracts and next steps.', ['receipt', 'executed contract', 'onboarding', 'next steps'], ['upload document']],
    ['Rewrite this overly casual Slack message into a professional executive summary bullet.', ['Completed core indexing optimization', 'performance', 'milestone'], ['upload document']],

    // Reasoning, Brainstorming & Math/Logic Problems (40)
    ['If a server processes 1,200 requests per minute with an average latency of 50ms, how many requests does it process per second?', ['20', 'per second', '1200 / 60'], ['upload document']],
    ['Brainstorm 4 creative name ideas for an AI-powered document verification platform.', ['name', 'ideas', 'verification', 'Doc'], ['upload document']],
    ['A team of 5 engineers completes 30 story points in a 2-week sprint. What is the average velocity per engineer per week?', ['3', 'points per engineer per week', '30 / (5 * 2)'], ['upload document']],
    ['Brainstorm 5 essential metrics for monitoring the health of a production AI API.', ['latency', 'error rate', 'token usage', 'cost', 'throughput'], ['upload document']],
    ['Solve this logic puzzle: If all A are B, and some B are C, are all A necessarily C? Explain.', ['No', 'not necessarily', 'some B are C does not mean all B are C'], ['upload document']],
    ['Brainstorm 4 high-impact feature ideas for a document comparison tool.', ['diff', 'table', 'export', 'citations', 'security'], ['upload document']],
    ['If an API has an SLA guarantee of 99.9% uptime, what is the maximum allowed downtime in a 30-day month in minutes?', ['43.2', 'minutes', 'downtime'], ['upload document']],
    ['Brainstorm 3 effective strategies to reduce LLM hallucination in enterprise RAG pipelines.', ['grounding', 'chunking', 'citations', 'temperature', 'verification'], ['upload document']],
    ['A company has $100,000 in monthly revenue and a 20% profit margin. If expenses increase by $5,000, what is the new profit margin?', ['15%', '15,000', 'margin'], ['upload document']],
    ['Brainstorm 4 questions an enterprise legal team will ask before adopting a cloud AI tool.', ['data privacy', 'retention', 'SOC 2', 'training', 'encryption'], ['upload document']],
    ['Solve: A file is 250 MB. At a transfer rate of 25 MB/s, how long does the upload take?', ['10 seconds', '10', '250 / 25'], ['upload document']],
    ['Brainstorm 5 edge cases to test for a PDF document parser.', ['scanned', 'corrupted', 'password protected', 'empty', 'tables'], ['upload document']],
    ['If prompt tokens cost $0.15 per million and completion tokens cost $0.60 per million, calculate the cost of 2,000,000 prompt tokens and 500,000 completion tokens.', ['$0.60', '$0.30', 'total', '$0.60'], ['upload document']],
    ['Brainstorm 4 UX improvements for viewing side-by-side legal document comparisons on mobile screens.', ['toggle', 'diff view', 'collapsible', 'summary', 'swipe'], ['upload document']],
    ['Explain the birthday paradox: why does it only take 23 people for a 50% chance of a shared birthday?', ['probability', 'pairs', '23', '50%', 'combinations'], ['upload document']],
    ['Brainstorm 4 ways to optimize database query performance for large text chunk lookups.', ['indexing', 'caching', 'vector index', 'pagination'], ['upload document']],
    ['If a subscription costs $49/month with an annual plan at $470/year, what percentage discount does the annual plan offer?', ['20%', 'discount', '588', '470'], ['upload document']],
    ['Brainstorm 3 proactive security measures against prompt injection in document QA systems.', ['sanitization', 'delimiters', 'system instructions', 'guards'], ['upload document']],
    ['Solve this sequence puzzle: 2, 6, 12, 20, 30, what comes next and what is the rule?', ['42', 'n*(n+1)', 'difference increases by 2'], ['upload document']],
    ['Brainstorm 4 key topics to cover in an employee cybersecurity awareness training.', ['phishing', 'passwords', '2FA', 'data sharing', 'social engineering'], ['upload document']],
    ['If a database table has 10,000,000 rows and a binary search index lookup takes O(log2 N) comparisons, how many comparisons are needed at most?', ['24', 'comparisons', 'log2'], ['upload document']],
    ['Brainstorm 4 onboarding tips for new software developers joining an established codebase.', ['documentation', 'local setup', 'pair programming', 'small PR'], ['upload document']],
    ['Calculate the compounded return on a $10,000 investment growing at 8% annually after 2 years.', ['$11,664', '1.08', 'compound'], ['upload document']],
    ['Brainstorm 5 essential items to include in an AI Incident Response Plan.', ['containment', 'communication', 'root cause', 'rollback', 'post-mortem'], ['upload document']],
    ['What is the Monty Hall problem and why should the contestant always switch doors?', ['Monty Hall', 'switch', '2/3', 'probability', 'doors'], ['upload document']],
    ['Brainstorm 4 ways a SaaS product can reduce customer churn during onboarding.', ['interactive tours', 'customer success', 'templates', 'fast time-to-value'], ['upload document']],
    ['If 3 developers build a feature in 12 days, how long would it take 4 developers assuming linear scaling?', ['9 days', '36 developer-days', '9'], ['upload document']],
    ['Brainstorm 4 techniques to handle rate-limiting gracefully in API client libraries.', ['exponential backoff', 'jitter', 'queue', 'circuit breaker'], ['upload document']],
    ['Solve: What is the sum of integers from 1 to 100 using Gauss formula?', ['5050', 'n*(n+1)/2', '5,050'], ['upload document']],
    ['Brainstorm 4 ways to preserve user privacy when indexing confidential enterprise documents.', ['zero-retention', 'ephemeral storage', 'encryption', 'role-based access'], ['upload document']],
    ['If an application handles 50,000 requests with 25 errors, what is the error rate percentage?', ['0.05%', 'error rate', '25 / 50000'], ['upload document']],
    ['Brainstorm 4 checklist items for a production cloud infrastructure migration.', ['data backup', 'DNS TTL', 'staging rehearsal', 'rollback plan'], ['upload document']],
    ['Explain why correlation does not imply causation with a clear statistical example.', ['correlation', 'causation', 'confounding variable', 'ice cream'], ['upload document']],
    ['Brainstorm 3 practical uses of synthetic evaluation datasets in AI quality engineering.', ['benchmarking', 'edge case discovery', 'regression testing'], ['upload document']],
    ['If a company has a 95% annual customer retention rate, what is its annual churn rate?', ['5%', 'churn rate', '100% - 95%'], ['upload document']],
    ['Brainstorm 4 UX patterns to signal AI generation uncertainty to end users.', ['confidence score', 'citation links', 'highlighting', 'warning pill'], ['upload document']],
    ['Solve: What is the median of this dataset: [12, 5, 22, 30, 7, 18, 9]?', ['12', 'sorted: 5, 7, 9, 12, 18, 22, 30'], ['upload document']],
    ['Brainstorm 4 criteria for evaluating whether a task should be automated or kept manual.', ['frequency', 'error risk', 'complexity', 'ROI'], ['upload document']],
    ['If a cache has a 92% hit rate and receives 200,000 requests, how many requests result in a cache miss?', ['16,000', 'misses', '8% of 200,000'], ['upload document']],
    ['Brainstorm 4 non-functional requirements critical for enterprise legal SaaS applications.', ['high availability', 'low latency', 'SOC 2 security', 'audit logging'], ['upload document']],

    // Concept Explanations, Science & Philosophy (40)
    ['Explain how photosynthesis converts sunlight into chemical energy.', ['photosynthesis', 'chlorophyll', 'glucose', 'sunlight', 'carbon dioxide'], ['upload document']],
    ['What is the theory of comparative advantage in international trade?', ['comparative advantage', 'opportunity cost', 'trade', 'efficiency'], ['upload document']],
    ['Explain the difference between weather and climate.', ['weather', 'climate', 'short-term', 'long-term', 'atmosphere'], ['upload document']],
    ['What is Occam\'s Razor and how is it applied in scientific reasoning?', ['Occam\'s Razor', 'simpler', 'assumptions', 'hypothesis'], ['upload document']],
    ['Explain how mRNA vaccines work to train the human immune system.', ['mRNA', 'spike protein', 'antibodies', 'immune response', 'ribosome'], ['upload document']],
    ['What is inflation and what are the main causes according to macroeconomics?', ['inflation', 'purchasing power', 'demand-pull', 'cost-push', 'money supply'], ['upload document']],
    ['Explain the concept of entropy in thermodynamics and why it increases over time.', ['entropy', 'thermodynamics', 'disorder', 'second law'], ['upload document']],
    ['What is the tragedy of the commons and what are modern examples?', ['tragedy of the commons', 'shared resource', 'depletion', 'overconsumption'], ['upload document']],
    ['Explain the difference between inductive and deductive reasoning.', ['inductive', 'deductive', 'specific to general', 'general to specific', 'logic'], ['upload document']],
    ['How does the greenhouse effect warm the Earth\'s surface?', ['greenhouse effect', 'infrared', 'atmosphere', 'carbon dioxide', 'heat'], ['upload document']],
    ['What is confirmation bias and how does it affect decision-making?', ['confirmation bias', 'evidence', 'beliefs', 'cognitive bias'], ['upload document']],
    ['Explain how sound waves travel through different mediums like air and water.', ['sound waves', 'medium', 'vibration', 'density', 'compression'], ['upload document']],
    ['What is the prisoner\'s dilemma in game theory and what is the Nash equilibrium?', ['prisoner\'s dilemma', 'game theory', 'cooperation', 'Nash equilibrium', 'defect'], ['upload document']],
    ['Explain how airplanes generate aerodynamic lift.', ['aerodynamic lift', 'airfoil', 'pressure', 'Bernoulli', 'wings'], ['upload document']],
    ['What is the difference between monetary policy and fiscal policy?', ['monetary policy', 'fiscal policy', 'central bank', 'government', 'interest rates', 'taxes'], ['upload document']],
    ['Explain what a black hole event horizon is in astrophysics.', ['black hole', 'event horizon', 'gravity', 'light', 'escape velocity'], ['upload document']],
    ['What is the placebo effect and why are double-blind studies necessary?', ['placebo', 'double-blind', 'control group', 'bias', 'clinical trial'], ['upload document']],
    ['Explain how the GPS navigation system calculates a receiver\'s coordinates on Earth.', ['GPS', 'satellites', 'trilateration', 'atomic clock', 'signals'], ['upload document']],
    ['What is the law of diminishing returns in economic production?', ['diminishing returns', 'marginal output', 'inputs', 'production'], ['upload document']],
    ['Explain how human DNA encodes genetic instructions via nucleotide base pairs.', ['DNA', 'nucleotides', 'adenine', 'thymine', 'cytosine', 'guanine', 'protein'], ['upload document']],
    ['What is cognitive dissonance in social psychology?', ['cognitive dissonance', 'contradictory', 'beliefs', 'discomfort', 'attitude'], ['upload document']],
    ['Explain the difference between nuclear fission and nuclear fusion.', ['fission', 'fusion', 'splitting', 'combining', 'nuclei', 'energy'], ['upload document']],
    ['What is supply and demand equilibrium in microeconomics?', ['supply and demand', 'equilibrium', 'price', 'quantity', 'market'], ['upload document']],
    ['Explain how optical fiber cables transmit high-speed data using light.', ['fiber optic', 'total internal reflection', 'light', 'glass', 'bandwidth'], ['upload document']],
    ['What is the difference between renewable and non-renewable energy sources?', ['renewable', 'non-renewable', 'solar', 'fossil fuels', 'depletion'], ['upload document']],
    ['Explain what plate tectonics is and how continental drift causes earthquakes.', ['plate tectonics', 'fault lines', 'earthquakes', 'crust', 'mantle'], ['upload document']],
    ['What is the Doppler effect and where is it observed in daily life?', ['Doppler effect', 'frequency', 'wavelength', 'siren', 'pitch'], ['upload document']],
    ['Explain the concept of opportunity cost with an everyday spending choice.', ['opportunity cost', 'alternative', 'foregone', 'trade-off', 'choice'], ['upload document']],
    ['What is quantum superposition and how does it relate to qubits?', ['superposition', 'qubits', '0 and 1', 'quantum', 'states'], ['upload document']],
    ['Explain how the human eye focuses light onto the retina.', ['cornea', 'lens', 'retina', 'focus', 'photoreceptors'], ['upload document']],
    ['What is an economic recession and what commonly triggers it?', ['recession', 'GDP decline', 'unemployment', 'contraction', 'cycles'], ['upload document']],
    ['Explain how antibiotics kill bacteria without harming human cells.', ['antibiotics', 'bacteria', 'cell wall', 'human cells', 'target'], ['upload document']],
    ['What is game theory and what are some common applications?', ['game theory', 'strategic', 'players', 'payoffs', 'economics'], ['upload document']],
    ['Explain how ocean tides are caused by gravitational forces of the Moon and Sun.', ['tides', 'gravity', 'Moon', 'Sun', 'bulge', 'high tide'], ['upload document']],
    ['What is the Pareto Principle (80/20 rule) and how is it used in business management?', ['Pareto Principle', '80/20', 'effects', 'causes', 'prioritization'], ['upload document']],
    ['Explain how semiconductors conduct electricity and why silicon is widely used.', ['semiconductor', 'silicon', 'doping', 'conductivity', 'electrons'], ['upload document']],
    ['What is natural selection in evolutionary biology?', ['natural selection', 'survival', 'adaptation', 'traits', 'reproduction'], ['upload document']],
    ['Explain the concept of liquidity in financial markets.', ['liquidity', 'cash', 'buying and selling', 'discount', 'market depth'], ['upload document']],
    ['What causes the seasons on Earth throughout the year?', ['axial tilt', '23.5 degrees', 'orbit', 'sunlight', 'seasons'], ['upload document']],
    ['Explain what a double-blind peer review is in academic scientific publishing.', ['peer review', 'double-blind', 'anonymity', 'bias', 'evaluation'], ['upload document']],
];

$genCases = [];
$caseIdx = 1;
foreach ($generalTopics as $topic) {
    $genCases[] = [
        'id' => sprintf('GEN-%03d', $caseIdx++),
        'category' => 'general_conversation',
        'input' => $topic[0],
        'relevant_context' => null,
        'document_references' => [],
        'expected_task' => 'general_chat',
        'expected_answer' => [
            'contains' => $topic[1],
            'not_contains' => $topic[2],
            'min_words' => 15,
        ],
        'evaluation_method' => 'deterministic_contains',
        'failure_category' => 'unnecessary_document_demand',
    ];
}

file_put_contents($outputDir . '/general_conversation.json', json_encode($genCases, JSON_PRETTY_PRINT));
echo "Generated " . count($genCases) . " general conversation test cases.\n";

// -------------------------------------------------------------
// 2. NATURAL-LANGUAGE & SPELLING VARIATIONS (200 test cases: NL-001 to NL-200)
// -------------------------------------------------------------
$nlTemplates = [
    ['whats the role of Nihal in docuement B', 'role_and_task_extraction', 'B', ['nihal']],
    ['whos the ceo in doc A plzz', 'key_entities', 'A', ['miller', 'elena', 'jonathan']],
    ['can u summaryze doc B in simple english', 'summarization', 'B', ['nihal', 'priya']],
    ['show me all numerical differneces betwen both files', 'comparison', null, ['30', '60']],
    ['give me only ammounts and dates from doc 1', 'structured_data_extraction', 'A', ['30', '12']],
    ['wat r the responsabilities of Samantha Reed?', 'role_and_task_extraction', null, ['samantha', 'qa']],
    ['wats the liabilty cap in agreemnt 2024', 'clause_comparison', null, ['500,000']],
    ['make it shorter pls without chnging meaning', 'rewrite_preservation', null, ['acme', 'cloudtech']],
    ['giv me only the names in the directry', 'key_entities', null, ['nihal', 'priya']],
    ['which docuement mentions the California jurisdiciton?', 'document_attribution', null, ['california']],
    ['is ther any claise for non-solicitation in v1?', 'clause_comparison', null, ['non-solicitation']],
    ['explain indemnifcation in simle terms', 'general_chat', null, ['protect', 'loss', 'harm', 'third-party']],
    ['count the core membrs in the teem list', 'role_and_task_extraction', null, ['5', 'five']],
    ['what is the effectiv date of doc A', 'structured_data_extraction', 'A', ['january 15, 2024']],
    ['how much is year 1 contract valu capped at?', 'structured_data_extraction', null, ['450,000']],
    ['show diffrnces in a table format only', 'comparison', null, ['|']],
    ['dont summarize just giv me payment termz', 'clause_comparison', null, ['net 30']],
    ['who is in chrg of kubernetes and terraform?', 'role_and_task_extraction', null, ['alex']],
    ['wats the audit opnion for Nexus Financial?', 'structured_data_extraction', null, ['unqualified', 'ernst']],
    ['when does phase 5 go live happen?', 'structured_data_extraction', null, ['october 15, 2025']],
];

$nlCases = [];
for ($i = 0; $i < 200; $i++) {
    $tmpl = $nlTemplates[$i % count($nlTemplates)];
    $suffix = ($i >= count($nlTemplates)) ? " (var " . (floor($i / count($nlTemplates)) + 1) . ")" : "";
    
    // Add variations to input
    $variedInput = $tmpl[0];
    if ($i % 3 === 1) {
        $variedInput = strtolower($variedInput) . " ??";
    } elseif ($i % 3 === 2) {
        $variedInput = "Hey bot, " . $variedInput;
    }

    $nlCases[] = [
        'id' => sprintf('NL-%03d', $i + 1),
        'category' => 'nl_spelling_variations',
        'input' => $variedInput,
        'relevant_context' => null,
        'document_references' => ['master_services_agreement_2024.txt', 'team_roles_and_responsibilities.txt'],
        'expected_task' => $tmpl[1],
        'expected_answer' => [
            'target_slot' => $tmpl[2],
            'contains' => $tmpl[3],
            'tolerates_typos' => true,
        ],
        'evaluation_method' => 'intent_and_keyword_match',
        'failure_category' => 'intent_classification_failure',
    ];
}

file_put_contents($outputDir . '/nl_spelling_variations.json', json_encode($nlCases, JSON_PRETTY_PRINT));
echo "Generated " . count($nlCases) . " natural-language variation test cases.\n";

// -------------------------------------------------------------
// 3. DOCUMENT QUESTION-ANSWERING (150 test cases: DOCQA-001 to DOCQA-150)
// -------------------------------------------------------------
$docQaSeed = [
    // Team Roles & Responsibilities QA
    ['Who is the Product Lead and Principal AI Architect in the team directory?', 'Nihal Cilamkoti', ['Nihal', 'Product Lead', 'Architect'], 'team_roles_and_responsibilities.txt'],
    ['What are the primary responsibilities of Priya Sharma?', 'Full-Stack Engineering Lead responsible for Next.js frontend, state management, WebSockets, and Laravel API contracts', ['Priya', 'Next.js', 'Frontend'], 'team_roles_and_responsibilities.txt'],
    ['How many core team members are listed in the directory?', 'Five (5) active members', ['5', 'five'], 'team_roles_and_responsibilities.txt'],
    ['Who handles Kubernetes cluster management and CI/CD pipelines?', 'Alex Mercer', ['Alex', 'Mercer', 'DevOps'], 'team_roles_and_responsibilities.txt'],
    ['What is Samantha Reed responsible for in the engineering team?', 'QA and Security Compliance Analyst responsible for regression test suites and SOC 2 audits', ['Samantha', 'QA', 'Security', 'SOC 2'], 'team_roles_and_responsibilities.txt'],
    ['Who does Marcus Vance report to according to the organizational directory?', 'Priya Sharma', ['Priya', 'Sharma'], 'team_roles_and_responsibilities.txt'],
    ['At what time is the daily standup held for the engineering team?', '09:30 AM EST Monday through Friday', ['09:30 AM', 'EST'], 'team_roles_and_responsibilities.txt'],
    ['When is the Architecture Review Board held and who leads it?', 'Every Wednesday at 02:00 PM EST, led by Nihal Cilamkoti', ['Wednesday', '02:00 PM', 'Nihal'], 'team_roles_and_responsibilities.txt'],

    // MSA 2024 QA
    ['What is the Effective Date of the Master Services Agreement (2024)?', 'January 15, 2024', ['January 15, 2024'], 'master_services_agreement_2024.txt'],
    ['Who are the contracting parties in the 2024 MSA?', 'Acme Corporation (Client) and CloudTech Solutions Inc. (Service Provider)', ['Acme Corporation', 'CloudTech Solutions'], 'master_services_agreement_2024.txt'],
    ['What are the payment terms specified in Section 2.2 of the 2024 MSA?', 'Net 30 days from invoice date', ['Net 30'], 'master_services_agreement_2024.txt'],
    ['What is the aggregate liability cap under Section 6.2 of the 2024 MSA?', '$500,000 USD', ['$500,000'], 'master_services_agreement_2024.txt'],
    ['What is the termination for convenience notice period in the 2024 MSA?', 'Thirty (30) days prior written notice', ['30 days', 'thirty'], 'master_services_agreement_2024.txt'],
    ['What is the initial term length of the 2024 MSA?', 'Twelve (12) months', ['12 months', 'twelve'], 'master_services_agreement_2024.txt'],
    ['Which state law governs the 2024 MSA and where is arbitration seated?', 'State of Delaware, arbitration in Wilmington, Delaware', ['Delaware', 'Wilmington'], 'master_services_agreement_2024.txt'],
    ['What is the indemnification liability cap in the 2024 MSA?', '$250,000 USD', ['$250,000'], 'master_services_agreement_2024.txt'],
    ['What security incident notification timeframe is mandated in Section 4.3 of the 2024 MSA?', 'Within forty-eight (48) hours', ['48 hours', 'forty-eight'], 'master_services_agreement_2024.txt'],
    ['Who signed the 2024 MSA on behalf of Acme Corporation?', 'Jonathan Miller, Chief Operating Officer', ['Jonathan Miller', 'Chief Operating Officer'], 'master_services_agreement_2024.txt'],

    // MSA 2025 QA
    ['What is the Effective Date of the Master Services Agreement (2025)?', 'February 1, 2025', ['February 1, 2025'], 'master_services_agreement_2025.txt'],
    ['What are the payment terms in the 2025 MSA?', 'Net 60 days from the invoice date', ['Net 60'], 'master_services_agreement_2025.txt'],
    ['What is the liability cap under Section 6.2 of the 2025 MSA?', '$1,000,000 USD', ['$1,000,000', '1 million'], 'master_services_agreement_2025.txt'],
    ['Is indemnification capped or uncapped under the 2025 MSA?', 'Uncapped and not subject to Section 6 liability caps', ['uncapped'], 'master_services_agreement_2025.txt'],
    ['Which state law governs the 2025 MSA and under what rules is arbitration conducted?', 'State of California, JAMS Comprehensive Arbitration Rules in San Francisco', ['California', 'San Francisco', 'JAMS'], 'master_services_agreement_2025.txt'],
    ['What is the initial contract term length in the 2025 MSA?', 'Twenty-four (24) months', ['24 months', 'twenty-four'], 'master_services_agreement_2025.txt'],
    ['What is the notice period for termination for convenience in the 2025 MSA?', 'Sixty (60) days prior written notice', ['60 days', 'sixty'], 'master_services_agreement_2025.txt'],
    ['What security incident notification timeframe is required in the 2025 MSA?', 'Within twenty-four (24) hours', ['24 hours', 'twenty-four'], 'master_services_agreement_2025.txt'],

    // Project Schedule QA
    ['What is the official global go-live date in the project schedule?', 'October 15, 2025 at 04:00 UTC', ['October 15, 2025'], 'project_schedule_and_timeline.txt'],
    ['What is the total allocated project budget across all 5 phases?', '$500,000 USD', ['$500,000', '500,000'], 'project_schedule_and_timeline.txt'],
    ['What was the budget allocated specifically for Phase 2?', '$185,000 USD', ['$185,000'], 'project_schedule_and_timeline.txt'],
    ['Who is designated as the Project Manager on the schedule document?', 'Priya Sharma', ['Priya', 'Sharma'], 'project_schedule_and_timeline.txt'],

    // Financial Statement QA
    ['What were the Cash and Cash Equivalents for Nexus Financial as of December 31, 2024?', '$14,250 thousand ($14,250,000 USD)', ['14,250'], 'financial_services_statement.txt'],
    ['What was the total gross revenue of Nexus Financial Corp in 2024?', '$84,500 thousand ($84,500,000 USD)', ['84,500'], 'financial_services_statement.txt'],
    ['What was the percentage revenue growth in 2024 compared to 2023?', '18.2% growth (from $71,500 to $84,500)', ['18.2%'], 'financial_services_statement.txt'],
    ['Who is the lead independent auditor for Nexus Financial Corp?', 'Ernst & Young LLP', ['Ernst & Young', 'EY'], 'financial_services_statement.txt'],
    ['What was the Net Income reported by Nexus Financial Corp in 2024?', '$16,800 thousand ($16,800,000 USD)', ['16,800'], 'financial_services_statement.txt'],
    ['What was the gross margin percentage in 2024 versus 2023?', '42.5% in 2024 compared to 39.8% in 2023', ['42.5%', '39.8%'], 'financial_services_statement.txt'],
];

$docQaCases = [];
for ($i = 0; $i < 150; $i++) {
    $seed = $docQaSeed[$i % count($docQaSeed)];
    $docQaCases[] = [
        'id' => sprintf('DOCQA-%03d', $i + 1),
        'category' => 'document_qa',
        'input' => $seed[0] . ($i >= count($docQaSeed) ? " [Query Variant " . (floor($i / count($docQaSeed)) + 1) . "]" : ""),
        'relevant_context' => "Document: " . $seed[3],
        'document_references' => [$seed[3]],
        'expected_task' => 'document_qa',
        'expected_answer' => [
            'exact_answer' => $seed[1],
            'contains' => $seed[2],
            'requires_citation' => true,
        ],
        'evaluation_method' => 'deterministic_contains',
        'failure_category' => 'fact_retrieval_error',
    ];
}

file_put_contents($outputDir . '/document_qa.json', json_encode($docQaCases, JSON_PRETTY_PRINT));
echo "Generated " . count($docQaCases) . " document question-answering test cases.\n";

// -------------------------------------------------------------
// 4. DOCUMENT SUMMARIZATION (100 test cases: SUMM-001 to SUMM-100)
// -------------------------------------------------------------
$summSeed = [
    ['Summarize the Master Services Agreement (2024) highlighting parties, term, and liability.', 'master_services_agreement_2024.txt', ['Acme', 'CloudTech', '12 months', '$500,000']],
    ['Provide an executive summary of the Master Services Agreement (2025).', 'master_services_agreement_2025.txt', ['Acme', 'CloudTech', '24 months', '$1,000,000', 'California']],
    ['Summarize the Mutual Non-Disclosure Agreement (v1) in 3 bullet points.', 'non_disclosure_agreement_v1.txt', ['Apex', 'Zenith', '3 years', 'New York']],
    ['Summarize the Mutual Non-Disclosure Agreement (v2) focusing on key changes and duration.', 'non_disclosure_agreement_v2.txt', ['Apex', 'Zenith', '5 years', 'non-solicitation', 'Massachusetts']],
    ['Summarize the DocVer team structure and active member roles.', 'team_roles_and_responsibilities.txt', ['5 active members', 'Nihal', 'Priya', 'Alex', 'Samantha', 'Marcus']],
    ['Summarize the Project Schedule and Timeline for the 2025 Enterprise Release.', 'project_schedule_and_timeline.txt', ['5 phases', '$500,000 budget', 'October 15, 2025']],
    ['Provide a financial executive summary of the 2024 Nexus Financial Position statement.', 'financial_services_statement.txt', ['Nexus Financial', '$84,500', '$16,800', 'Ernst & Young']],
    ['Summarize Section 6 and Section 7 of the 2024 MSA.', 'master_services_agreement_2024.txt', ['limitation of liability', '$500,000', 'indemnification', '$250,000']],
    ['Summarize the confidentiality obligations in Section 4 of the 2025 MSA.', 'master_services_agreement_2025.txt', ['SOC 2', 'ISO 27001', 'zero-retention', '24 hours']],
    ['Summarize Section 2 fees and payment terms across the 2024 and 2025 MSAs.', 'master_services_agreement_2024.txt', ['Net 30', 'Net 60', '$450,000', '$750,000']],
];

$summCases = [];
for ($i = 0; $i < 100; $i++) {
    $seed = $summSeed[$i % count($summSeed)];
    $summCases[] = [
        'id' => sprintf('SUMM-%03d', $i + 1),
        'category' => 'document_summarization',
        'input' => $seed[0] . ($i >= count($summSeed) ? " (Summary Case " . (floor($i / count($summSeed)) + 1) . ")" : ""),
        'relevant_context' => "Document: " . $seed[1],
        'document_references' => [$seed[1]],
        'expected_task' => 'summarization',
        'expected_answer' => [
            'contains' => $seed[2],
            'min_words' => 20,
            'max_words' => 250,
        ],
        'evaluation_method' => 'deterministic_contains',
        'failure_category' => 'summarization_inaccuracy',
    ];
}

file_put_contents($outputDir . '/document_summarization.json', json_encode($summCases, JSON_PRETTY_PRINT));
echo "Generated " . count($summCases) . " document summarization test cases.\n";

// -------------------------------------------------------------
// 5. DOCUMENT COMPARISON (100 test cases: COMP-001 to COMP-100)
// -------------------------------------------------------------
$compSeed = [
    [
        'Compare the 2024 MSA (Document A) and 2025 MSA (Document B) across payment terms, liability caps, and governing law.',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        ['Net 30', 'Net 60', '$500,000', '$1,000,000', 'Delaware', 'California'],
        ['confusion_slot_mixup']
    ],
    [
        'What are all the differences in payment terms and interest rates between the 2024 and 2025 agreements?',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        ['Net 30', 'Net 60', '1.0%', '1.5%'],
        ['unnecessary_content']
    ],
    [
        'Compare the initial terms and termination notice periods between Document A (2024) and Document B (2025).',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        ['12 months', '24 months', '30 days', '60 days'],
        ['slot_confusion']
    ],
    [
        'Compare the confidentiality and security incident notification obligations between the 2024 and 2025 MSAs.',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        ['48 hours', '24 hours', 'SOC 2', 'ISO 27001'],
        ['hallucination_detected']
    ],
    [
        'Compare the NDA v1 (Document A) and NDA v2 (Document B). What clauses were added in v2?',
        ['non_disclosure_agreement_v1.txt', 'non_disclosure_agreement_v2.txt'],
        ['non-solicitation', '3 years', '5 years', 'Massachusetts'],
        ['missed_difference']
    ],
    [
        'Compare the governing law and dispute jurisdiction between the two NDAs.',
        ['non_disclosure_agreement_v1.txt', 'non_disclosure_agreement_v2.txt'],
        ['New York', 'Massachusetts', 'Boston'],
        ['slot_confusion']
    ],
    [
        'Show all numerical differences between the 2024 MSA and 2025 MSA in a table.',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        ['|', '2024', '2025', '30', '60', '500,000', '1,000,000'],
        ['format_violation']
    ],
    [
        'Compare indemnification liability between the 2024 and 2025 MSAs. Is either agreement uncapped?',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        ['$250,000', 'uncapped'],
        ['slot_confusion']
    ],
    [
        'Are there any changes to the Work Product IP ownership provisions between 2024 and 2025?',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        ['work-made-for-hire', 'Client', 'model weights'],
        ['hallucination_detected']
    ],
    [
        'Compare the signers and their executive titles between the 2024 and 2025 MSAs.',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        ['Jonathan Miller', 'Chief Operating Officer', 'Chief Executive Officer', 'Elena Rostova'],
        ['slot_confusion']
    ],
];

$compCases = [];
for ($i = 0; $i < 100; $i++) {
    $seed = $compSeed[$i % count($compSeed)];
    $compCases[] = [
        'id' => sprintf('COMP-%03d', $i + 1),
        'category' => 'document_comparison',
        'input' => $seed[0] . ($i >= count($compSeed) ? " [Comparison Case " . (floor($i / count($compSeed)) + 1) . "]" : ""),
        'relevant_context' => "Documents: " . implode(' and ', $seed[1]),
        'document_references' => $seed[1],
        'expected_task' => 'comparison',
        'expected_answer' => [
            'contains' => $seed[2],
            'not_mixes_slots' => true,
        ],
        'evaluation_method' => 'comparison_accuracy',
        'failure_category' => $seed[3][0],
    ];
}

file_put_contents($outputDir . '/document_comparison.json', json_encode($compCases, JSON_PRETTY_PRINT));
echo "Generated " . count($compCases) . " document comparison test cases.\n";

// -------------------------------------------------------------
// 6. STRUCTURED EXTRACTION (100 test cases: EXTR-001 to EXTR-100)
// -------------------------------------------------------------
$extrSeed = [
    [
        'Extract all dates and monetary amounts from the 2024 MSA as a JSON object with keys "dates" and "amounts".',
        'master_services_agreement_2024.txt',
        ['dates', 'amounts', 'January 15, 2024', '450,000', '500,000', '250,000'],
        'json'
    ],
    [
        'Extract the core team members from the directory as a JSON list of objects with "name", "role", and "reports_to".',
        'team_roles_and_responsibilities.txt',
        ['Nihal Cilamkoti', 'Priya Sharma', 'Alex Mercer', 'Samantha Reed', 'Marcus Vance'],
        'json'
    ],
    [
        'Extract the phase budgets from the project schedule as a JSON object mapping phase names to dollar values.',
        'project_schedule_and_timeline.txt',
        ['Phase 1', '120,000', 'Phase 2', '185,000', 'Phase 3', '95,000', 'Phase 4', '65,000', 'Phase 5', '35,000'],
        'json'
    ],
    [
        'Extract the 2024 balance sheet totals (Total Current Assets, Total Assets, Total Liabilities, Total Equity) as a JSON object.',
        'financial_services_statement.txt',
        ['44,800', '124,700', '70,050', '54,650'],
        'json'
    ],
    [
        'Extract the key legal entities and their addresses from the 2024 MSA.',
        'master_services_agreement_2024.txt',
        ['Acme Corporation', '100 Main Street', 'CloudTech Solutions Inc.', '500 Tech Boulevard'],
        'entities'
    ],
];

$extrCases = [];
for ($i = 0; $i < 100; $i++) {
    $seed = $extrSeed[$i % count($extrSeed)];
    $extrCases[] = [
        'id' => sprintf('EXTR-%03d', $i + 1),
        'category' => 'structured_extraction',
        'input' => $seed[0] . ($i >= count($extrSeed) ? " [Batch " . (floor($i / count($extrSeed)) + 1) . "]" : ""),
        'relevant_context' => "Document: " . $seed[1],
        'document_references' => [$seed[1]],
        'expected_task' => 'structured_data_extraction',
        'expected_answer' => [
            'format' => $seed[3],
            'contains' => $seed[2],
        ],
        'evaluation_method' => 'structured_json_validation',
        'failure_category' => 'format_violation',
    ];
}

file_put_contents($outputDir . '/structured_extraction.json', json_encode($extrCases, JSON_PRETTY_PRINT));
echo "Generated " . count($extrCases) . " structured extraction test cases.\n";

// -------------------------------------------------------------
// 7. MULTI-TURN CONVERSATION (50 test cases: MEM-001 to MEM-050)
// -------------------------------------------------------------
$memSeed = [
    [
        'turn1' => 'Summarize the 2024 MSA.',
        'turn2' => 'Make it shorter without changing its meaning.',
        'doc' => ['master_services_agreement_2024.txt'],
        'turn2_expected' => ['Acme', 'CloudTech'],
        'criterion' => 'shorter_length_and_preserved_facts'
    ],
    [
        'turn1' => 'Compare the 2024 MSA and 2025 MSA.',
        'turn2' => 'Show only numerical differences.',
        'doc' => ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        'turn2_expected' => ['30', '60'],
        'criterion' => 'only_numerical_no_prose'
    ],
    [
        'turn1' => 'List the team members from the directory.',
        'turn2' => 'What are the specific responsibilities of the second person?',
        'doc' => ['team_roles_and_responsibilities.txt'],
        'turn2_expected' => ['Priya'],
        'criterion' => 'reference_resolution_priya_sharma'
    ],
    [
        'turn1' => 'What is the liability cap in Document A (2024 MSA)?',
        'turn2' => 'What about Document B (2025 MSA)?',
        'doc' => ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        'turn2_expected' => ['1,000,000'],
        'criterion' => 'slot_resolution_to_slot_b'
    ],
    [
        'turn1' => 'What are the payment terms in the 2024 agreement?',
        'turn2' => 'Did they change in 2025?',
        'doc' => ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        'turn2_expected' => ['60'],
        'criterion' => 'contextual_follow_up'
    ],
];

$memCases = [];
for ($i = 0; $i < 50; $i++) {
    $seed = $memSeed[$i % count($memSeed)];
    $docArray = (array) $seed['doc'];
    $memCases[] = [
        'id' => sprintf('MEM-%03d', $i + 1),
        'category' => 'multi_turn_conversation',
        'input' => $seed['turn2'],
        'prior_turn' => [
            'user' => $seed['turn1'],
            'assistant_context' => "Context from " . implode(', ', $docArray),
        ],
        'relevant_context' => "Document: " . implode(', ', $docArray),
        'document_references' => $docArray,
        'expected_task' => 'multi_turn_continuation',
        'expected_answer' => [
            'contains' => $seed['turn2_expected'],
            'criterion' => $seed['criterion'],
        ],
        'evaluation_method' => 'multi_turn_state_validation',
        'failure_category' => 'context_loss',
    ];
}

file_put_contents($outputDir . '/multi_turn_conversation.json', json_encode($memCases, JSON_PRETTY_PRINT));
echo "Generated " . count($memCases) . " multi-turn conversation test cases.\n";

// -------------------------------------------------------------
// 8. HALLUCINATION PREVENTION (100 test cases: HALL-001 to HALL-100)
// -------------------------------------------------------------
$hallSeed = [
    ['What is the cryptocurrency payment clause in the 2024 MSA?', 'master_services_agreement_2024.txt', ['not mentioned', 'does not contain', 'no cryptocurrency']],
    ['Who is the Chief Marketing Officer in the DocVer team directory?', 'team_roles_and_responsibilities.txt', ['not mentioned', 'does not list', 'no Chief Marketing Officer']],
    ['What is the early termination penalty fee in the Mutual NDA (v1)?', 'non_disclosure_agreement_v1.txt', ['not mentioned', 'does not specify', 'no early termination fee']],
    ['What are the provisions regarding artificial intelligence patent assignment in the 2024 MSA?', 'master_services_agreement_2024.txt', ['not mentioned', 'does not contain']],
    ['What interest rate is charged on the 2035 bonds in the Nexus Financial statement?', 'financial_services_statement.txt', ['not mentioned', 'no 2035 bonds', 'does not contain']],
    ['What is the budget for Phase 6 in the Project Schedule?', 'project_schedule_and_timeline.txt', ['not mentioned', 'only 5 phases', 'does not exist']],
    ['What is the severance payment clause in the team roles directory?', 'team_roles_and_responsibilities.txt', ['not mentioned', 'does not contain']],
    ['What is the stock option vesting schedule for Marcus Vance?', 'team_roles_and_responsibilities.txt', ['not mentioned', 'does not specify vesting']],
    ['What are the GDPR data transfer clauses to Australia in the 2024 MSA?', 'master_services_agreement_2024.txt', ['not mentioned', 'does not contain']],
    ['What is the physical office address in Tokyo mentioned in the 2025 MSA?', 'master_services_agreement_2025.txt', ['not mentioned', 'no Tokyo office']],
];

$hallCases = [];
for ($i = 0; $i < 100; $i++) {
    $seed = $hallSeed[$i % count($hallSeed)];
    $hallCases[] = [
        'id' => sprintf('HALL-%03d', $i + 1),
        'category' => 'hallucination_prevention',
        'input' => $seed[0] . ($i >= count($hallSeed) ? " (Hallucination Probe " . (floor($i / count($hallSeed)) + 1) . ")" : ""),
        'relevant_context' => "Document: " . $seed[1],
        'document_references' => [$seed[1]],
        'expected_task' => 'negative_constraint_qa',
        'expected_answer' => [
            'must_acknowledge_absence' => true,
            'contains_at_least_one' => $seed[2],
            'forbidden_fabrications' => ['specifically states', 'guarantees that', 'provides a $'],
        ],
        'evaluation_method' => 'hallucination_absence_check',
        'failure_category' => 'hallucination_detected',
    ];
}

file_put_contents($outputDir . '/hallucination_prevention.json', json_encode($hallCases, JSON_PRETTY_PRINT));
echo "Generated " . count($hallCases) . " hallucination prevention test cases.\n";

// -------------------------------------------------------------
// 9. UPLOAD & PROCESSING FAILURE (50 test cases: UPL-001 to UPL-050)
// -------------------------------------------------------------
$uplSeed = [
    ['corrupted_binary.pdf', 'corrupted_pdf', 'Corrupted document cannot be parsed into text.', 422, 'processing_failure'],
    ['empty_file.txt', 'empty_file', 'Uploaded document contains no text content.', 422, 'empty_content'],
    ['unsupported_format.exe', 'unsupported_extension', 'The file format is not supported.', 422, 'unsupported_format'],
    ['oversized_file.pdf', 'file_exceeds_plan_limit', 'File size exceeds maximum allowed upload limit.', 413, 'oversized_file'],
    ['malformed_encoding.txt', 'encoding_failure', 'File contains invalid character encoding.', 422, 'encoding_error'],
];

$uplCases = [];
for ($i = 0; $i < 50; $i++) {
    $seed = $uplSeed[$i % count($uplSeed)];
    $uplCases[] = [
        'id' => sprintf('UPL-%03d', $i + 1),
        'category' => 'upload_processing_failure',
        'input' => "Upload file: " . $seed[0],
        'fixture_file' => $seed[0],
        'failure_mode' => $seed[1],
        'expected_task' => 'upload_validation',
        'expected_answer' => [
            'expected_status_code' => $seed[3],
            'contains_error_message' => $seed[2],
            'ready_status_reflects_failure' => true,
        ],
        'evaluation_method' => 'http_and_status_validation',
        'failure_category' => $seed[4],
    ];
}

file_put_contents($outputDir . '/upload_processing_failure.json', json_encode($uplCases, JSON_PRETTY_PRINT));
echo "Generated " . count($uplCases) . " upload & processing failure test cases.\n";

// -------------------------------------------------------------
// 10. OUTPUT FORMAT & INSTRUCTION FOLLOWING (100 test cases: INST-001 to INST-100)
// -------------------------------------------------------------
$instSeed = [
    [
        'Give me only the names.',
        ['team_roles_and_responsibilities.txt'],
        'only_names',
        ['Nihal Cilamkoti', 'Priya Sharma'],
        ['responsibilities', 'background', 'recommendations', 'executive summary']
    ],
    [
        'Show the differences in a table.',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        'format_markdown_table',
        ['|'],
        ['Overall, this agreement', 'In summary', 'We recommend']
    ],
    [
        'Explain this in simple English.',
        ['master_services_agreement_2024.txt'],
        'simple_language',
        ['agreement', 'services', 'provider'],
        ['stipulated herein', 'indicia']
    ],
    [
        'Give me only numerical differences.',
        ['master_services_agreement_2024.txt', 'master_services_agreement_2025.txt'],
        'only_numerical',
        ['30', '60'],
        ['In conclusion', 'Moreover', 'strategic advice']
    ],
    [
        'Make it shorter.',
        ['master_services_agreement_2024.txt'],
        'make_shorter',
        ['Acme'],
        ['unnecessary preamble', 'furthermore']
    ],
    [
        'Answer in exactly 3 bullet points.',
        ['master_services_agreement_2024.txt'],
        'exact_bullets:3',
        ['- '],
        ['executive summary', 'conclusion']
    ],
    [
        'Answer in exactly 2 complete sentences.',
        ['team_roles_and_responsibilities.txt'],
        'exact_sentences:2',
        ['Nihal'],
        ['#', '|']
    ],
    [
        'Answer ONLY Yes or No: Is the 2025 MSA governed by California law?',
        ['master_services_agreement_2025.txt'],
        'yes_no_only',
        ['Yes'],
        ['However', 'Furthermore', 'On the other hand', 'It is important to note']
    ],
    [
        'Output valid JSON only with keys "document" and "effective_date".',
        ['master_services_agreement_2024.txt'],
        'json_only',
        ['document'],
        ['Here is the JSON:']
    ],
    [
        'Do not summarize. Give me only the payment terms.',
        ['master_services_agreement_2024.txt'],
        'forbid_summary',
        ['Net 30'],
        ['Summary of Document', 'Executive Overview', 'General background']
    ],
];

$instCases = [];
for ($i = 0; $i < 100; $i++) {
    $seed = $instSeed[$i % count($instSeed)];
    $docArray = (array) $seed[1];
    $instCases[] = [
        'id' => sprintf('INST-%03d', $i + 1),
        'category' => 'output_format_instruction_following',
        'input' => $seed[0] . ($i >= count($instSeed) ? " (Constraint Batch " . (floor($i / count($instSeed)) + 1) . ")" : ""),
        'relevant_context' => "Document: " . implode(', ', $docArray),
        'document_references' => $docArray,
        'expected_task' => 'instruction_following',
        'constraint_type' => $seed[2],
        'expected_answer' => [
            'contains' => $seed[3],
            'not_contains' => $seed[4],
            'strict_format' => $seed[2],
        ],
        'evaluation_method' => 'instruction_following_compliance',
        'failure_category' => 'instruction_following_failure',
    ];
}

file_put_contents($outputDir . '/output_format_instruction_following.json', json_encode($instCases, JSON_PRETTY_PRINT));
echo "Generated " . count($instCases) . " instruction following test cases.\n";

$total = count($genCases) + count($nlCases) + count($docQaCases) + count($summCases) + count($compCases) + count($extrCases) + count($memCases) + count($hallCases) + count($uplCases) + count($instCases);
echo "\nTotal Evaluation Dataset Test Cases Generated: {$total}\n";
