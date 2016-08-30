# Life Sciences Identifier (LSID) resolver

## Background
[LSIDs](https://en.wikipedia.org/wiki/LSID) are a type of globally unique identifier that emerged from the life sciences community. It was adopted by several taxonomic databases in the mid 2000’s. When a LSID is resolved it returns information about the entity identified by that LSID (e.g., a taxonomic name), typically in [RDF](https://en.wikipedia.org/wiki/Resource_Description_Framework).

For a variety of reasons the adoption of LSIDs has been limited. They are non-trivial to set up, require specialised software to resolve (such as this resolver), and return RDF rather than human-readable content. However, because LSID servers still exist it is useful to have a LSID resolver.

## Functionality
This code supports resolving LSIDs and returning metadata as RDF in various forms: [JSON-LD](https://en.wikipedia.org/wiki/JSON-LD), [N-triples](https://en.wikipedia.org/wiki/N-Triples), and [RDF/XML](https://en.wikipedia.org/wiki/RDF/XML).

You can also get RDF directly in one of two ways:

1. Append a file extension to the LSID (e.g., “.nt” for N-Triples).

2. Content negotiation (e.g., “application/ld+json” for JSON-LD).

## Installation and configuration

Simply unpack the source code in a web-accessible directory. Make sure that the cache directory is writable by the web browser, e.g.

```
chmod 777 cache
```

Make sure that the RewriteBase directive in the .htaccess file corresponds to the base URL for the web server hosting the resolver.

## Notes on JSON-LD

Want this to be as clean and JSON-like as possible. Struggling to handle IPNI and nomenclatural codes.

Following JSON-LD will recreate triples with nomenclatural code as URI, other examples (e.g., “botanical” by itself) don’t work.

```

    “nomenclaturalCode”:  “http://rs.tdwg.org/ontology/voc/TaxonName#nomenclaturalCode”,
    “tn”: “http://rs.tdwg.org/ontology/voc/TaxonName#”,
    “botanical”: “tn:botanical”,

“nomenclaturalCode”: { “@id” : “tn:botanical”},
```

See http://tinyurl.com/hjjqpp8




