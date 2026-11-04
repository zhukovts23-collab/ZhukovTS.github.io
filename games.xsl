<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="html" encoding="UTF-8"/>
<xsl:param name="sort" select="'title'"/><xsl:param name="genre" select="''"/><xsl:param name="q" select="''"/>
<xsl:template match="/">
<html lang="ru"><head><meta charset="utf-8"/><title>XML-каталог GameVerse</title><link rel="stylesheet" href="../css/style.css"/></head><body class="xml-frame">
<p>Источник: games.xml · Оценки редакции, шкала 1–5 · Год первого выпуска</p>
<div class="table-wrap"><table class="table"><thead><tr><th>Игра</th><th>Жанр</th><th>Платформы</th><th>Год</th><th>Оценка</th><th>Особенность</th></tr></thead><tbody>
<xsl:for-each select="gameCatalog/game[($genre='' or genre=$genre) and contains(translate(title,'ABCDEFGHIJKLMNOPQRSTUVWXYZАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ','abcdefghijklmnopqrstuvwxyzабвгдеёжзийклмнопрстуфхцчшщъыьэюя'),$q)]">
<xsl:sort select="number(year) * number($sort='year') + number(rating) * number($sort='rating')" data-type="number" order="descending"/><xsl:sort select="title"/>
<tr><td><xsl:value-of select="title"/></td><td><xsl:value-of select="genre"/></td><td><xsl:value-of select="platforms"/></td><td><xsl:value-of select="year"/></td><td><xsl:value-of select="rating"/></td><td><xsl:value-of select="tag"/></td></tr>
</xsl:for-each></tbody></table></div>
<xsl:if test="not(gameCatalog/game[($genre='' or genre=$genre) and contains(translate(title,'ABCDEFGHIJKLMNOPQRSTUVWXYZАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ','abcdefghijklmnopqrstuvwxyzабвгдеёжзийклмнопрстуфхцчшщъыьэюя'),$q)])"><p>По запросу ничего не найдено.</p></xsl:if>
</body></html></xsl:template></xsl:stylesheet>
