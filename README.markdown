Field: Multilingual Image upload
==============

The multilingual version of the specialized field for image uploads.

* Version: 0.1beta
* Build Date: 2011-12-13
* Authors:
	- [Xander Group](http://www.xanderadvertising.ro)
	- Vlad Ghita
* Requirements:
	- Symphony 2.0 or above
	- [Image Upload extension](https://github.com/vlad-ghita/image_upload) at least version 1.1.4
	- [Frontend Localisation] (https://github.com/vlad-ghita/frontend_localisation)
	- If using maximum width / height, [JIT](https://github.com/symphonycms/jit_image_manipulation) is required

Thank you all other Symphony & Extensions developers for your inspirational work.



# 1 About #

The multilingual version of the specialized Image Upload field for images: 

- it accepts only images: bmp, jpg, jpeg, png and gif.
- optional, set a minimum width and / or height. If 0 or empty, no minimum limit will exist.
- optional, set a maximum width and / or height. If 0 or empty, no maximum resize limit will exist.
- optional, it will create unique filenames.

**NB:** The resize takes places upon save, no matter new entry or edit entry.

<br />
**VERY IMPORTANT**<br />
This extension depends on [Frontend Localisation](https://github.com/vlad-ghita/frontend_localisation). From there it draws it's Frontend language information. This way I'm trying to decouple my multilingual stuff from various Language drivers out there.<br />
Get Frontend Localisation, a language driver (Language Redirect for example) and you're good to go.



# 2 Installation #

1. Upload the `multilingual_image_upload` folder found in this archive to your Symphony `extensions` folder.    
2. Enable it by selecting `Field: Multilingual Image upload` under `System -> Extensions`, choose Enable from the with-selected menu, then click Apply.
3. You can now add `Multilingual Image upload` field to your sections.




# 3 Compatibility #

           Symphony | Field: Multilingual Image Upload
--------------------|----------------
        2.0 — *     | [latest](https://github.com/vlad-ghita/multilingual_image_upload)

Field: Image Upload | Field: Multilingual Image Upload
--------------------|----------------
      [1.1.4](https://github.com/vlad-ghita/image_upload) — *     | [latest](https://github.com/vlad-ghita/multilingual_image_upload)




# 4 Changelog #

- 0.1beta : 13 dec 2011
    * Initial beta release