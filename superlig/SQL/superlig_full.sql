-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 26 Mar 2026, 12:05:13
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `superlig_full`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ayar`
--

CREATE TABLE `ayar` (
  `id` int(11) NOT NULL,
  `hafta` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `ayar`
--

INSERT INTO `ayar` (`id`, `hafta`) VALUES
(1, 20);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `maclar`
--

CREATE TABLE `maclar` (
  `id` int(11) NOT NULL,
  `ev` int(11) DEFAULT NULL,
  `dep` int(11) DEFAULT NULL,
  `ev_skor` int(11) DEFAULT NULL,
  `dep_skor` int(11) DEFAULT NULL,
  `hafta` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `maclar`
--

INSERT INTO `maclar` (`id`, `ev`, `dep`, `ev_skor`, `dep_skor`, `hafta`) VALUES
(1541, 1, 11, 1, 1, 1),
(1542, 2, 12, 5, 1, 1),
(1543, 3, 13, 2, 1, 1),
(1544, 4, 14, 0, 1, 1),
(1545, 5, 15, 4, 1, 1),
(1546, 6, 16, 2, 0, 1),
(1547, 7, 17, 1, 0, 1),
(1548, 8, 18, 2, 1, 1),
(1549, 9, 19, 1, 0, 1),
(1550, 10, 20, 0, 1, 1),
(1551, 1, 10, 5, 1, 2),
(1552, 20, 11, 1, 1, 2),
(1553, 2, 12, 2, 0, 2),
(1554, 3, 13, 0, 0, 2),
(1555, 4, 14, 2, 0, 2),
(1556, 5, 15, 1, 1, 2),
(1557, 6, 16, 4, 1, 2),
(1558, 7, 17, 2, 0, 2),
(1559, 8, 18, 2, 0, 2),
(1560, 9, NULL, 1, 0, 2),
(1561, 1, 9, 1, 2, 3),
(1562, 18, 10, 1, 0, 3),
(1563, 20, 11, 0, 1, 3),
(1564, 2, 12, 4, 1, 3),
(1565, 3, 13, 1, 1, 3),
(1566, 4, 14, 1, 0, 3),
(1567, 5, 15, 0, 1, 3),
(1568, 6, 16, 0, 0, 3),
(1569, 7, NULL, 1, 0, 3),
(1570, 8, NULL, 3, 0, 3),
(1571, 1, 8, 0, 0, 4),
(1572, 16, 9, 0, 1, 4),
(1573, 18, 10, 0, 1, 4),
(1574, 20, 11, 2, 0, 4),
(1575, 2, 12, 2, 0, 4),
(1576, 3, 13, 1, 1, 4),
(1577, 4, 14, 0, 0, 4),
(1578, 5, NULL, 2, 0, 4),
(1579, 6, NULL, 1, 0, 4),
(1580, 7, NULL, 2, 0, 4),
(1581, 1, 7, 4, 2, 5),
(1582, 14, 8, 0, 2, 5),
(1583, 16, 9, 0, 0, 5),
(1584, 18, 10, 0, 0, 5),
(1585, 20, 11, 0, 1, 5),
(1586, 2, 12, 3, 0, 5),
(1587, 3, NULL, 1, 0, 5),
(1588, 4, NULL, 1, 0, 5),
(1589, 5, NULL, 3, 0, 5),
(1590, 6, NULL, 0, 0, 5),
(1591, 1, 6, 0, 3, 6),
(1592, 12, 7, 0, 0, 6),
(1593, 14, 8, 1, 2, 6),
(1594, 16, 9, 0, 1, 6),
(1595, 18, 10, 0, 0, 6),
(1596, 20, NULL, 1, 0, 6),
(1597, 2, NULL, 3, 0, 6),
(1598, 3, NULL, 3, 0, 6),
(1599, 4, NULL, 1, 0, 6),
(1600, 5, NULL, 1, 0, 6),
(1601, 1, 5, 2, 4, 7),
(1602, 10, 6, 0, 0, 7),
(1603, 12, 7, 0, 1, 7),
(1604, 14, 8, 2, 2, 7),
(1605, 16, NULL, 1, 0, 7),
(1606, 18, NULL, 2, 0, 7),
(1607, 20, NULL, 0, 0, 7),
(1608, 2, NULL, 3, 0, 7),
(1609, 3, NULL, 3, 0, 7),
(1610, 4, NULL, 4, 0, 7),
(1611, 1, 4, 0, 4, 8),
(1612, 8, 5, 0, 3, 8),
(1613, 10, 6, 1, 3, 8),
(1614, 12, NULL, 1, 0, 8),
(1615, 14, NULL, 0, 0, 8),
(1616, 16, NULL, 1, 0, 8),
(1617, 18, NULL, 2, 0, 8),
(1618, 20, NULL, 2, 0, 8),
(1619, 2, NULL, 6, 0, 8),
(1620, 3, NULL, 5, 0, 8),
(1621, 1, 3, 4, 6, 9),
(1622, 6, 4, 1, 2, 9),
(1623, 8, NULL, 2, 0, 9),
(1624, 10, NULL, 1, 0, 9),
(1625, 12, NULL, 2, 0, 9),
(1626, 14, NULL, 0, 0, 9),
(1627, 16, NULL, 0, 0, 9),
(1628, 18, NULL, 2, 0, 9),
(1629, 20, NULL, 1, 0, 9),
(1630, 2, NULL, 5, 0, 9),
(1631, 1, 2, 3, 3, 10),
(1632, 4, NULL, 2, 0, 10),
(1633, 6, NULL, 1, 0, 10),
(1634, 8, NULL, 0, 0, 10),
(1635, 10, NULL, 2, 0, 10),
(1636, 12, NULL, 2, 0, 10),
(1637, 14, NULL, 2, 0, 10),
(1638, 16, NULL, 1, 0, 10),
(1639, 18, NULL, 2, 0, 10),
(1640, 20, NULL, 1, 0, 10),
(1641, 1, NULL, 0, 0, 11),
(1642, 2, NULL, 4, 0, 11),
(1643, 4, NULL, 5, 0, 11),
(1644, 6, NULL, 2, 0, 11),
(1645, 8, NULL, 1, 0, 11),
(1646, 10, NULL, 0, 0, 11),
(1647, 12, NULL, 2, 0, 11),
(1648, 14, NULL, 0, 0, 11),
(1649, 16, NULL, 1, 0, 11),
(1650, 18, NULL, 2, 0, 11),
(1651, 1, NULL, 2, 0, 12),
(1652, 18, NULL, 1, 0, 12),
(1653, 2, NULL, 1, 0, 12),
(1654, 4, NULL, 2, 0, 12),
(1655, 6, NULL, 1, 0, 12),
(1656, 8, NULL, 1, 0, 12),
(1657, 10, NULL, 3, 0, 12),
(1658, 12, NULL, 1, 0, 12),
(1659, 14, NULL, 1, 0, 12),
(1660, 1, NULL, 1, 0, 13),
(1661, 14, NULL, 2, 0, 13),
(1662, 18, NULL, 2, 0, 13),
(1663, 2, NULL, 5, 0, 13),
(1664, 4, NULL, 2, 0, 13),
(1665, 6, NULL, 3, 0, 13),
(1666, 8, NULL, 2, 0, 13),
(1667, 10, NULL, 1, 0, 13),
(1668, 1, NULL, 4, 0, 14),
(1669, 10, NULL, 3, 0, 14),
(1670, 14, NULL, 1, 0, 14),
(1671, 18, NULL, 1, 0, 14),
(1672, 2, NULL, 6, 0, 14),
(1673, 4, NULL, 1, 0, 14),
(1674, 6, NULL, 2, 0, 14),
(1675, 1, NULL, 3, 0, 15),
(1676, 6, NULL, 0, 0, 15),
(1677, 10, NULL, 0, 0, 15),
(1678, 14, NULL, 1, 0, 15),
(1679, 18, NULL, 0, 0, 15),
(1680, 2, NULL, 3, 0, 15),
(1681, 1, NULL, 6, 0, 16),
(1682, 2, NULL, 2, 0, 16),
(1683, 6, NULL, 1, 0, 16),
(1684, 10, NULL, 1, 0, 16),
(1685, 14, NULL, 2, 0, 16),
(1686, 1, NULL, 5, 0, 17),
(1687, 14, NULL, 1, 0, 17),
(1688, 2, NULL, 7, 0, 17),
(1689, 6, NULL, 3, 0, 17),
(1690, 1, NULL, 2, 0, 18),
(1691, 6, NULL, 3, 0, 18),
(1692, 14, NULL, 2, 0, 18),
(1693, 1, NULL, 4, 0, 19),
(1694, 14, NULL, 0, 0, 19),
(1695, 11, 1, NULL, NULL, 20),
(1696, 12, 2, NULL, NULL, 20),
(1697, 13, 3, NULL, NULL, 20),
(1698, 14, 4, NULL, NULL, 20),
(1699, 15, 5, NULL, NULL, 20),
(1700, 16, 6, NULL, NULL, 20),
(1701, 17, 7, NULL, NULL, 20),
(1702, 18, 8, NULL, NULL, 20),
(1703, 19, 9, NULL, NULL, 20),
(1704, 20, 10, NULL, NULL, 20),
(1705, 10, 1, NULL, NULL, 21),
(1706, 11, 20, NULL, NULL, 21),
(1707, 12, 2, NULL, NULL, 21),
(1708, 13, 3, NULL, NULL, 21),
(1709, 14, 4, NULL, NULL, 21),
(1710, 15, 5, NULL, NULL, 21),
(1711, 16, 6, NULL, NULL, 21),
(1712, 17, 7, NULL, NULL, 21),
(1713, 18, 8, NULL, NULL, 21),
(1714, NULL, 9, NULL, NULL, 21),
(1715, 9, 1, NULL, NULL, 22),
(1716, 10, 18, NULL, NULL, 22),
(1717, 11, 20, NULL, NULL, 22),
(1718, 12, 2, NULL, NULL, 22),
(1719, 13, 3, NULL, NULL, 22),
(1720, 14, 4, NULL, NULL, 22),
(1721, 15, 5, NULL, NULL, 22),
(1722, 16, 6, NULL, NULL, 22),
(1723, NULL, 7, NULL, NULL, 22),
(1724, NULL, 8, NULL, NULL, 22),
(1725, 8, 1, NULL, NULL, 23),
(1726, 9, 16, NULL, NULL, 23),
(1727, 10, 18, NULL, NULL, 23),
(1728, 11, 20, NULL, NULL, 23),
(1729, 12, 2, NULL, NULL, 23),
(1730, 13, 3, NULL, NULL, 23),
(1731, 14, 4, NULL, NULL, 23),
(1732, NULL, 5, NULL, NULL, 23),
(1733, NULL, 6, NULL, NULL, 23),
(1734, NULL, 7, NULL, NULL, 23),
(1735, 7, 1, NULL, NULL, 24),
(1736, 8, 14, NULL, NULL, 24),
(1737, 9, 16, NULL, NULL, 24),
(1738, 10, 18, NULL, NULL, 24),
(1739, 11, 20, NULL, NULL, 24),
(1740, 12, 2, NULL, NULL, 24),
(1741, NULL, 3, NULL, NULL, 24),
(1742, NULL, 4, NULL, NULL, 24),
(1743, NULL, 5, NULL, NULL, 24),
(1744, NULL, 6, NULL, NULL, 24),
(1745, 6, 1, NULL, NULL, 25),
(1746, 7, 12, NULL, NULL, 25),
(1747, 8, 14, NULL, NULL, 25),
(1748, 9, 16, NULL, NULL, 25),
(1749, 10, 18, NULL, NULL, 25),
(1750, NULL, 20, NULL, NULL, 25),
(1751, NULL, 2, NULL, NULL, 25),
(1752, NULL, 3, NULL, NULL, 25),
(1753, NULL, 4, NULL, NULL, 25),
(1754, NULL, 5, NULL, NULL, 25),
(1755, 5, 1, NULL, NULL, 26),
(1756, 6, 10, NULL, NULL, 26),
(1757, 7, 12, NULL, NULL, 26),
(1758, 8, 14, NULL, NULL, 26),
(1759, NULL, 16, NULL, NULL, 26),
(1760, NULL, 18, NULL, NULL, 26),
(1761, NULL, 20, NULL, NULL, 26),
(1762, NULL, 2, NULL, NULL, 26),
(1763, NULL, 3, NULL, NULL, 26),
(1764, NULL, 4, NULL, NULL, 26),
(1765, 4, 1, NULL, NULL, 27),
(1766, 5, 8, NULL, NULL, 27),
(1767, 6, 10, NULL, NULL, 27),
(1768, NULL, 12, NULL, NULL, 27),
(1769, NULL, 14, NULL, NULL, 27),
(1770, NULL, 16, NULL, NULL, 27),
(1771, NULL, 18, NULL, NULL, 27),
(1772, NULL, 20, NULL, NULL, 27),
(1773, NULL, 2, NULL, NULL, 27),
(1774, NULL, 3, NULL, NULL, 27),
(1775, 3, 1, NULL, NULL, 28),
(1776, 4, 6, NULL, NULL, 28),
(1777, NULL, 8, NULL, NULL, 28),
(1778, NULL, 10, NULL, NULL, 28),
(1779, NULL, 12, NULL, NULL, 28),
(1780, NULL, 14, NULL, NULL, 28),
(1781, NULL, 16, NULL, NULL, 28),
(1782, NULL, 18, NULL, NULL, 28),
(1783, NULL, 20, NULL, NULL, 28),
(1784, NULL, 2, NULL, NULL, 28),
(1785, 2, 1, NULL, NULL, 29),
(1786, NULL, 4, NULL, NULL, 29),
(1787, NULL, 6, NULL, NULL, 29),
(1788, NULL, 8, NULL, NULL, 29),
(1789, NULL, 10, NULL, NULL, 29),
(1790, NULL, 12, NULL, NULL, 29),
(1791, NULL, 14, NULL, NULL, 29),
(1792, NULL, 16, NULL, NULL, 29),
(1793, NULL, 18, NULL, NULL, 29),
(1794, NULL, 20, NULL, NULL, 29),
(1795, NULL, 1, NULL, NULL, 30),
(1796, NULL, 2, NULL, NULL, 30),
(1797, NULL, 4, NULL, NULL, 30),
(1798, NULL, 6, NULL, NULL, 30),
(1799, NULL, 8, NULL, NULL, 30),
(1800, NULL, 10, NULL, NULL, 30),
(1801, NULL, 12, NULL, NULL, 30),
(1802, NULL, 14, NULL, NULL, 30),
(1803, NULL, 16, NULL, NULL, 30),
(1804, NULL, 18, NULL, NULL, 30),
(1805, NULL, 1, NULL, NULL, 31),
(1806, NULL, 18, NULL, NULL, 31),
(1807, NULL, 2, NULL, NULL, 31),
(1808, NULL, 4, NULL, NULL, 31),
(1809, NULL, 6, NULL, NULL, 31),
(1810, NULL, 8, NULL, NULL, 31),
(1811, NULL, 10, NULL, NULL, 31),
(1812, NULL, 12, NULL, NULL, 31),
(1813, NULL, 14, NULL, NULL, 31),
(1814, NULL, 1, NULL, NULL, 32),
(1815, NULL, 14, NULL, NULL, 32),
(1816, NULL, 18, NULL, NULL, 32),
(1817, NULL, 2, NULL, NULL, 32),
(1818, NULL, 4, NULL, NULL, 32),
(1819, NULL, 6, NULL, NULL, 32),
(1820, NULL, 8, NULL, NULL, 32),
(1821, NULL, 10, NULL, NULL, 32),
(1822, NULL, 1, NULL, NULL, 33),
(1823, NULL, 10, NULL, NULL, 33),
(1824, NULL, 14, NULL, NULL, 33),
(1825, NULL, 18, NULL, NULL, 33),
(1826, NULL, 2, NULL, NULL, 33),
(1827, NULL, 4, NULL, NULL, 33),
(1828, NULL, 6, NULL, NULL, 33),
(1829, NULL, 1, NULL, NULL, 34),
(1830, NULL, 6, NULL, NULL, 34),
(1831, NULL, 10, NULL, NULL, 34),
(1832, NULL, 14, NULL, NULL, 34),
(1833, NULL, 18, NULL, NULL, 34),
(1834, NULL, 2, NULL, NULL, 34),
(1835, NULL, 1, NULL, NULL, 35),
(1836, NULL, 2, NULL, NULL, 35),
(1837, NULL, 6, NULL, NULL, 35),
(1838, NULL, 10, NULL, NULL, 35),
(1839, NULL, 14, NULL, NULL, 35),
(1840, NULL, 1, NULL, NULL, 36),
(1841, NULL, 14, NULL, NULL, 36),
(1842, NULL, 2, NULL, NULL, 36),
(1843, NULL, 6, NULL, NULL, 36),
(1844, NULL, 1, NULL, NULL, 37),
(1845, NULL, 6, NULL, NULL, 37),
(1846, NULL, 14, NULL, NULL, 37),
(1847, NULL, 1, NULL, NULL, 38),
(1848, NULL, 14, NULL, NULL, 38);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `takimlar`
--

CREATE TABLE `takimlar` (
  `id` int(11) NOT NULL,
  `takim_adi` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `takimlar`
--

INSERT INTO `takimlar` (`id`, `takim_adi`, `logo`) VALUES
(1, 'Galatasaray', 'logos/galatasaray.png'),
(2, 'Fenerbahçe', 'logos/fenerbahce.png'),
(3, 'Beşiktaş', NULL),
(4, 'Trabzonspor', NULL),
(5, 'Başakşehir', NULL),
(6, 'Adana Demirspor', NULL),
(7, 'Konyaspor', NULL),
(8, 'Sivasspor', NULL),
(9, 'Antalyaspor', NULL),
(10, 'Kayserispor', NULL),
(11, 'Gaziantep FK', NULL),
(12, 'Alanyaspor', NULL),
(13, 'Rizespor', NULL),
(14, 'Kasımpaşa', NULL),
(15, 'Hatayspor', NULL),
(16, 'Ankaragücü', NULL),
(17, 'Pendikspor', NULL),
(18, 'İstanbulspor', NULL),
(19, 'Samsunspor', NULL),
(20, 'Karagümrük', NULL);

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `ayar`
--
ALTER TABLE `ayar`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `maclar`
--
ALTER TABLE `maclar`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `takimlar`
--
ALTER TABLE `takimlar`
  ADD PRIMARY KEY (`id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `maclar`
--
ALTER TABLE `maclar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1849;

--
-- Tablo için AUTO_INCREMENT değeri `takimlar`
--
ALTER TABLE `takimlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
