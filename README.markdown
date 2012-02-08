Field: Multilingual Image upload
==============

The multilingual version of the specialized field for image uploads.

* Version: 1.3
* Build Date: 2012-02-07
* Authors:
	- [Xander Group](http://www.xanderadvertising.com)
	- Vlad Ghita
* Requirements:
	- Symphony 2.2.x
	- [Image Upload extension](https://github.com/vlad-ghita/image_upload)
	- [Frontend Localisation](https://github.com/vlad-ghita/frontend_localisation)
	- [JIT image manipulation](https://github.com/symphonycms/jit_image_manipulation)

Thank you all other Symphony & Extensions developers for your inspirational work.



## 1 About ##

The multilingual version of the specialized Image Upload field for images: 

- it accepts only images: bmp, jpg, jpeg, png and gif.
- optional, set a minimum width and / or height. If 0 or empty, no minimum limit will exist.
- optional, set a maximum width and / or height. If 0 or empty, no maximum resize limit will exist.
- optional, it will create unique filenames.
- optional, it will return value from reference language

**NB:** The resize takes places upon save, if needed, no matter new or edited entry.



## 2 Installation ##

1. Upload the `multilingual_image_upload` folder found in this archive to your Symphony `extensions` folder.    
2. Enable it by selecting `Field: Multilingual Image upload` under `System -> Extensions`, choose Enable from the with-selected menu, then click Apply.
3. You can now add `Multilingual Image upload` field to your sections.
