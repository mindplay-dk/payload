mindplay/payload
================

This library lets you encode and decode small data-payloads into strings
that can be safely used in filenames and URLs.

This can be useful for things like encoding resize/cropping-information into
image URLs, creating personalized URLs for sharing things, etc.

The generated strings contain a small checksum as an integrity-check *only* - do
**not** rely on this for "security by obscurity", the data-payload *can* be decoded
and is by definition *not* secure or private in any way.

With that said, there is an option to prevent brute-force attacks, e.g. using a
longer checksum and private salt - see [options](#options) below.

## Usage

The service itself has no dependencies:

```php
$service = new PayloadService();
```

To encode an array as a string:

```php
$string = $service->encode(["hello" => "world"]); // "cMIDaGVsbG89d29ybGQ"
```

And to decode the string back to an array:

```php
$data = $service->decode("cMIDaGVsbG89d29ybGQ") // ["hello" => "world"]
```

### Options

The constructor permits you to optionally enforce a maximum encoded length - this option
is disabled by default. If enabled, `encode()` will throw if the encoded string-length is
over the defined maximum.

You can optionally specify number of characters to append as a checksum - this is set to
`4` by default. If you don't care about URL integrity, you can set this to zero.

If you're concerned about brute-force attacks against URLs, you can increase the checksum
size, and optionally specify a private salt to seed the checksum - again, this does not
provide strong security, but enough to prevent e.g. brute-force attacks against image URLs.

Refer to the [source-code](src/PayloadService.php) for inline documentation of options.

### Limitations

Only strings and arrays can be encoded. If your data contains integers, these will
be converted to strings, and will arrive in string format when decoded.

### Some Advice

Avoid encoding strings such as filenames, if you can - because the data is
encoded in base64 format, it will increase in size, so a good filename strategy
could be (for example) using an encoded string a prefix or suffix to a filename.
