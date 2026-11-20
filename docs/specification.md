# Supported A2A specification

This package implements **A2A specification v1.0.1** (released 2026-05-26).

Protocol version advertised on the wire is `1.0` (`A2A-Version` and `AgentInterface.protocolVersion`). Patch versions of the specification do not participate in version negotiation.

Normative sources:

- https://a2a-protocol.org/latest/specification/
- https://github.com/a2aproject/A2A/blob/v1.0.1/specification/a2a.proto

JSON serialisation uses ProtoJSON (camelCase field names, `SCREAMING_SNAKE_CASE` enum values). HTTP+JSON uses `application/a2a+json` as recommended in v1.0.1.

gRPC is not implemented. See the README conformance table.
