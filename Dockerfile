# שימוש בגרסת PHP רשמית שכוללת כבר שרת Apache
FROM php:8.2-apache

# הפעלת מודולים בסיסיים של Apache ליתר ביטחון
RUN a2enmod rewrite headers

# העתקת כל הקבצים מהגיטהאב לתיקייה ש-Apache מגיש ממנה
COPY . /var/www/html/

# חשיפת פורט 80 (Render ידע לנתב אליו אוטומטית)
EXPOSE 80
