# Description

Invenir was a product site with the goal of being a direct equivalent to the popular worldwide trading site, Alibaba.com, but for companies in the United States. 

While developing this site, we obtained large index of manufacturing and textile goods companies based within the United States, and because we wanted to have a bulk of companies listed up-front upon launching the site, we needed a way to aggregate those companies' product data. 

So, given the fact this listing of U.S.-based companies included their websites, we needed to build a way to obtain product data and related images in an automated way. I hadn't thought of looking up pre-made scraping libraries, but since at the time I was more familiar with PHP, I decided to incorporate pQuery, which is a PHP library similar to jQuery for working with DOM when retrieving URLs. 

# Ranking Images

Since the Scraper wasn't terribly smart, we couldn't figure out context for images. It would be tough to decide, at the time, which images were for products and which were small, design-esque images, like blank padding-cell .gif files, and such. We decided that, a more-or-less good indicator of an actual product image, would be those <img> tags with alt attributes. The assumption was that, if the company wanted search engines to correlate their images with their product data, they'd have good alt attributes. So the presence of an alt attribute of any length was given a value of 1. 

So that was the barrier for entry. Following that, we kept the algorithm simple, and decided to weight higher-resolution images higher than low-resolution images. The assumption was, if we found a link to an image, the site was linking to a good-quality photo, and we wanted that. 

So if either of the width or height dimensions of the photo were greater than 600px, we added 3 to the image's score. If the image's width or height attributes were between 400px and 600px on either side, the image's score was increased by 2. If the image's dimensions were between 200px and 400px in either dimension, the image's score was increased by 1. Lastly, we checked the image's aspect ratio and we wanted to look for images whose ratios were more or less indicative of actual product photos with a 16:9 or 4:3 ratio. This would further help to eliminate any instances where we might have followed an image that was used more for site design than actual product photos. 
