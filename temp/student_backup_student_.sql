

DROP DATABASE student;



CREATE DATABASE /*!32312 IF NOT EXISTS*/ `student` /*!40100 DEFAULT CHARACTER SET latin1 */;
USE `student`;



CREATE TABLE `fyit` (
  `id` int(25) DEFAULT NULL,
  `f_name` varchar(551) DEFAULT NULL,
  `m_name` varchar(3000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO fyit VALUES("2147483647","ramu ds,fjdf gdjgf pdfjgpldkjgf dlgfkj dolgfkj;fdlkg d;lg;fd gldkg;ldfg dlgf;ldfgkfd;lkg;ldkfg dfgk;ldkg;ldfkg","rakesh");
INSERT INTO fyit VALUES("2","vijayd","tripathi");
INSERT INTO fyit VALUES("5","komal","kamalz");
INSERT INTO fyit VALUES("72","tripathi","tripathi");



CREATE TABLE `syit` (
  `id` int(100) DEFAULT NULL,
  `name` varchar(300) DEFAULT NULL,
  `contact` int(100) DEFAULT NULL,
  `address` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO syit VALUES("1","rohit","2147483647","thane");
INSERT INTO syit VALUES("12","rakesh","2147483647","mulund");
INSERT INTO syit VALUES("31","ram","2147483647","vashi");
INSERT INTO syit VALUES("41","raja","2147483647","bhandup");



CREATE TABLE `tyit` (
  `id` int(10) DEFAULT NULL,
  `f_name` varchar(40) DEFAULT NULL,
  `l_name` varchar(40) DEFAULT NULL,
  `contact_no` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO tyit VALUES("201","asif","hashmi","2147483647");
INSERT INTO tyit VALUES("203","harman","pal","2147483647");
INSERT INTO tyit VALUES("204","rohit","yadav","2147483647");
INSERT INTO tyit VALUES("205","rani","sing","2147483647");

