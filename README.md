# cta
Content-addressable storage for PHP. *work in progress!* (ALPHA version)

# Idea

## Save Contents
* Split Contents into chunks (of equal length)/save to ChunkStorage
* Concat ChunkHashes into FileStorage Entry
* Save header lines and FileStorage Entry CTA-Hash into UriStorage

## Read/Serve Contents
* Serve header lines from UriStorage (if for Browser/Download)
* Look up FileStorage Entry in UriStorage
* Look up ChunkHashes in FileStorage
* Concat chunks from ChunkStorage to output contents

# spec
# content-addressable - [1.3.6.1.4.1.37553.8.1.8.1.16606](https://registry.frdl.de/?goto=oid%3A1.3.6.1.4.1.37553.8.1.8.1.16606)
* [1.3.6.1.4.1.37553.8.1.8.1.16606](https://registry.frdl.de/?goto=oid%3A1.3.6.1.4.1.37553.8.1.8.1.16606)- CTA paradigmas where we do NOT track for hash collisions.
* [1.3.6.1.4.1.37553.8.1.8.1.16606.1.56234465](https://registry.frdl.de/?goto=oid%3A1.3.6.1.4.1.37553.8.1.8.1.16606.1.56234465) - To reduce the possibility of collisions we store the hash along with the content-size.
* [1.3.6.1.4.1.37553.8.1.8.1.16606.1.27200801029](https://registry.frdl.de/?goto=oid%3A1.3.6.1.4.1.37553.8.1.8.1.16606.1.27200801029) - Files/content is saved into chunks of the same size/length.
# Server - frdl\cta\Server::class
* UriStorage where we store references: uri[hash]<->file[hash]<->chunks[hashes]
* UriStorage where we store (and may serve) headers associated to a file.
