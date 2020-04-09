# Hosted Account services (hostacct)

This project was a reasonably successful attempt to provide scalable hosting services for hire to clients. It's a model in which each `account` is provisioned with any of a set of `services` containing web-based capabilities. Each account had a single `proxy` in front of its services, and that proxy handled the TLS and domain management functions. 

With today's availability of containers and the Caddy 2 server (which manages its own TLS), it's time to improve on this model. But we don't want to lose anything we had here, so we're archiving it as a `git` repository.
