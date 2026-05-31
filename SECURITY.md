# Security Policy

## Supported Versions

Security fixes target the `main` branch. This project does not currently
maintain long-term support release branches.

## Reporting a Vulnerability

Please do not open a public issue for suspected security vulnerabilities.
Instead, contact the maintainer privately with:

- A concise description of the issue
- Steps to reproduce or a proof of concept
- Affected versions or commits, if known
- Any suggested mitigation

The maintainer will acknowledge the report, assess impact, and coordinate a
fix before public disclosure when appropriate.

## Dependency Security

Frontend and backend dependency audits are part of CI:

```bash
make audit
```

Automated dependency update pull requests are configured through Dependabot.

