# Cross-References to Other Codebases

**Purpose:** Links to related documentation and work in other codebases within the Astacala Rescue workspace.

## Related Documentation

### Mobile App References
- Link to mobile app documentation when backend changes affect mobile functionality
- Authentication system coordination
- API contract changes that impact mobile client

### Web App References  
- Link to web app documentation when backend changes affect web functionality
- Shared database considerations
- API compatibility for web client

### Integration Coordination
- Links to workspace-level integration documentation
- Cross-platform feature coordination
- Strategic decisions affecting multiple codebases

## How to Use This Folder

When working on backend features that affect other codebases:

1. **Create primary documentation** in the appropriate backend documentation folder
2. **Create cross-reference files** in this folder linking to the primary documentation
3. **Include impact summary** specific to how the backend changes affect other codebases
4. **Link to integration coordination** if workspace-level coordination is needed

## Cross-Reference File Format

```markdown
# [Feature Name] - Backend Cross-Reference

**Primary Documentation:** [Link to main documentation in backend]
**Related Work:** [Links to related work in other codebases]
**Integration Impact:** [Summary of how this affects other platforms]

## Backend Changes Summary
[Brief summary of backend changes]

## Impact on Mobile App
[How these changes affect mobile functionality]

## Impact on Web App  
[How these changes affect web functionality]

## Action Items for Other Codebases
- [ ] Mobile: [Specific actions needed]
- [ ] Web: [Specific actions needed]
```

---

**For complete multi-codebase documentation guidelines, see:**
`../../astacala_rescue_mobile/documentation/AI_AGENT_DOCUMENTATION_SYSTEM_V2.md`
