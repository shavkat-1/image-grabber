# Image Grabber

Веб-приложение на Symfony 7.2 для загрузки и обработки изображений со сторонних сайтов.

## Функциональность

- Парсинг изображений с любой веб-страницы по URL
- Фильтрация по минимальному размеру (ширина/высота)
- Автоматическое изменение размера до 200×200px (квадрат)
- Нанесение произвольного текста на картинку
- AJAX-форма без перезагрузки страницы
- Сохранение картинок и отображение при перезагрузке

## Стек

- PHP 8.3
- Symfony 7.2
- Nginx
- Docker / Docker Compose

## Установка и запуск

### Требования

- Docker
- Docker Compose

### Шаги

**1. Клонируй репозиторий:**
```bash
git clone https://github.com/shavkat-1/image-grabber.git
cd image-grabber
```

**2. Запусти контейнеры:**
```bash
docker-compose up -d --build
```

**3. Установи зависимости:**
```bash
docker-compose exec app composer install
```

**4. Создай папку для загрузок:**
```bash
docker-compose exec app mkdir -p /var/www/public/uploads
docker-compose exec app chmod 777 /var/www/public/uploads
```

**5. Очисти кэш:**
```bash
docker-compose exec app php bin/console cache:clear
```

**6. Открой в браузере:**
```
http://localhost:8080
```

## Использование

1. Введи URL страницы с картинками
2. Укажи минимальный размер (необязательно)
3. Напиши текст который появится на картинках (необязательно)
4. Нажми **Загрузить изображения**

## Структура проекта
```
image-grabber/
├── Dockerfile
├── docker-compose.yml
├── docker/
│   └── nginx/
│       └── default.conf
└── app/
    ├── src/
    │   ├── Controller/
    │   │   └── ImageGrabberController.php
    │   └── Service/
    │       └── ImageGrabberService.php
    ├── templates/
    │   └── index.html.twig
    └── public/
        └── uploads/
```