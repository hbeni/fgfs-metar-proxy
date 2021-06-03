fgfs-metar-proxy
================

FlightGear METAR web service with manual override functionality.

This webserver script will accept FlightGears METAR requests and proxy it to another provider.  
URLs have the form `[http-url]/[station]`, where "`[station]`" is filled by FlightGear with the nearest stadion ID.

Users can supply own METAR-strings by uploading more recent ones, using the upload site.


Install & Config
----------------------
- Deploy the files somewhere on a webserver
- Optionally adjust the `config.ini` file

To try it out locally you can spawn a PHP interpreter instance easily: `fgfs-metar-proxy$ php -S localhost:8080 -t .`


Using the proxy
---------------------
To use the proxy, you need to point FlightGear to its URL. Add this to your launcher:
```
--prop:/environment/realwx/metar-url=<yourServer>/[station]
```

Example: `--prop:/environment/realwx/metar-url=http://localhost:8080/[station]`


Uploading custom METAR strings
------------------------------
You can upload newer/custom METAR strings to the service. Those take precedence over the proxied ones (ie. override the proxied provider).

Just visit your servers deployed directory and add `?upload` to the URL. Example: `http://localhost:8080/?upload`

Uploaded strings are not validated and passed to flightgear directly, so be sure the METAR is valid.  
Uploaded strings will be invalidated after 4 hours automatically.