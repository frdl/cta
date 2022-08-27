# cta
Content-addressable storage for PHP. 

# Idea

## Save Contents
* Split Contents into chunks (of equal length)/save to ChunkStorage
* Concat ChunkHashes into FileStorage Entry
* Save header lines and FileStorage Entry CTA-Hash into UriStorage

# Read/Serve Contents
* Serve header lines from UriStorage (if for Browser/Download)
* Look up FileStorage Entry in UriStorage
* Look up ChunkHashes in FileStorage
* Concat chunks from ChunkStorage to output contents
