# Speccy Animation parser

Library for parsing GIF-file, SCA-file or ZIP-archive with ZX Spectrum screen files (6912 bytes) into ZX Spectrum animation source code

## Requires 

* PHP 5.6 or above
* Composer

## GIF-file requirements

* Two colors only: approximately black paper, approximately white ink
* No internal compression allowed

## SCA-file requirements

* Only version 1 and payload type 0 are supported

## Running web application in docker

```
docker run -p 80:80 nyuk/speccy_animation_parser
```

Then open http://localhost

## CLI from docker image

```
docker run -v C:\Docs\gifs:/app -w /app nyuk/speccy_animation_parser memsave animation.gif output_folder 
```

## CLI with PHP

```
php cmd.php fast animation.gif output_folder 
```

Use `--help` arg for more information

## Online demo

https://nyuk.retroscene.org/animation_parser_zx
