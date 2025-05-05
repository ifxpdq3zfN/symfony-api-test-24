# Solution

Generate a jwt token and copy it to (mac) clipboard:
```shell
echo Bearer $(curl -X POST --location "http://localhost:8080/authentication" \
    -H "Content-Type: application/json" \
    -d '{
            "username": "mfindel@vp-felder.de",
            "password": "hommes"
        }'|jq --raw-output '.token')|pbcopy
```

## Implementation Details

### Soft Delete Implementation
- Customer deletion: Implemented using `std.tbl_kunden.geloescht` flag
- Address deletion:
    - Uses `geloescht` flag in address detail table
    - Affects all customers assigned to the address

### Database Configuration
- Development database used for testing (utilizing existing data as fixtures)
- Required permissions granted to:
    - `sec.user_id_seq`
    - `std.adresse_adresse_id_seq`

### API Design Decisions
- `/foo/kunden/{customerId}/user`: Implemented as single-item GET endpoint (one user per customer)
- `/foo/kunden/{customerId}/adressen/{addressId}/details`: Implemented as single-item GET endpoint (one detail record per customer address)

## Technical Limitations

### Doctrine Integration Issues
- Cannot rely on `std.tbl_kunden.id` default value
    - Solution: Custom ID generator (8 random uppercase characters based on UUID)
- No `public.bundesland` table access for `web` user
    - Solution: Created enum based on database data

## Questions and Open Items

### Questions
- Should 'DELETE /foo/adressen/{id}' delete the address for all customers?

### Performance Considerations
Authentication performance impact on tests could be improved by:
- Simplifying cryptography
- Disabling jwt token for tests (partially)
- Introducing long-lived test tokens
- Optimizing token refresh logic

