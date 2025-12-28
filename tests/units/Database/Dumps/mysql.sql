CREATE TABLE user_type (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    label VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO user_type (label) VALUES ('admin'), ('user'), ('guest');

CREATE TABLE user (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    login VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(100) NOT NULL,
    type INT NOT NULL REFERENCES user_type(id)
);
INSERT INTO user (login, password, type) VALUES ('root', '$2y$12\$CPWrjBtTfHIBDMcRA3yexu2.LnBP5dmqHcUWxAiJAljNI1TnD5Tri', 1);


CREATE TABLE module (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    label VARCHAR(50) NOT NULL UNIQUE
);
INSERT INTO module (label) VALUES ('product'), ('order'), ('crm'), ('admin');

CREATE TABLE module_user (
    user INTEGER NOT NULL REFERENCES user(id),
    module INTEGER NOT NULL,
    FOREIGN KEY (module) REFERENCES module(id)
);
INSERT INTO module_user (user, module) VALUES (1, 4);

CREATE TABLE product (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    name VARCHAR(200) UNIQUE NOT NULL,
    price_dollar DECIMAL(10,5) NULL
);

CREATE TABLE product_manager (
    product INTEGER NOT NULL REFERENCES product(id),
    manager VARCHAR(200) NOT NULL,
    UNIQUE (product, manager)
);

CREATE TABLE addition (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    a FLOAT NOT NULL,
    b FLOAT NOT NULL,
    result FLOAT AS (a+b)
);