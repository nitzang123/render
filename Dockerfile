# שימוש בגרסת PHP רשמית שכוללת כבר שרת Apache
FROM php:8.2-apache

# הפעלת מודולים של Apache
RUN a2enmod rewrite headers

# הגדרת תיקיית העבודה
WORKDIR /var/www/html

# העתקת הקבצים (עדיף להעתיק רק מה שצריך אם יש הרבה קבצים)
COPY . /var/www/html/

# מתן הרשאות קריאה וכתיבה לשרת ה-Apache
RUN chown -R www-data:www-data /var/www/html

# חשיפת פורט 80
EXPOSE 80

# הפעלה מחדש של Apache כדי להבטיח הגדרות נכונות
CMD ["apache2-foreground"]
