-- Library System database schema
-- Import this in Laragon's HeidiSQL / phpMyAdmin (SQL tab), or run: mysql -u root < library_db.sql

CREATE DATABASE IF NOT EXISTS library_db;
USE library_db;

DROP TABLE IF EXISTS borrow_return;
DROP TABLE IF EXISTS book;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(100) NOT NULL
);

CREATE TABLE book (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    year INT,
    genre VARCHAR(100),
    publisher VARCHAR(255),
    book_content TEXT
);

CREATE TABLE borrow_return (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    book_id INT NOT NULL,
    borrow_date DATETIME NOT NULL,
    return_date DATETIME NULL,
    FOREIGN KEY (book_id) REFERENCES book(book_id)
);

-- Sample accounts (username prefix decides role: AM. = Admin, TC. = Teacher, SD. = Student)
INSERT INTO users (username, email, password) VALUES
('AM.Admin', 'admin@library.edu', 'admin123'),
('AM.Cruz', 'cruz.admin@library.edu', 'admin123'),
('TC.Reyes', 'reyes.teacher@library.edu', 'teacher123'),
('TC.Santos', 'santos.teacher@library.edu', 'teacher123'),
('SD.Juan', 'juan.student@library.edu', 'student123'),
('SD.Maria', 'maria.student@library.edu', 'student123'),
('SD.Pedro', 'pedro.student@library.edu', 'student123'),
('SD.Ana', 'ana.student@library.edu', 'student123');

-- Sample book catalog: popular, well-known books that are all in the PUBLIC DOMAIN
-- (first published before ~1930, so copyright has expired and they're free to use/read/share).
-- book_content holds a plot synopsis, not the full novel text - full books are far too
-- long to store as a single field here, but every title below has its complete text
-- freely available at Project Gutenberg (gutenberg.org) if you want to link to it.
INSERT INTO book (title, author, year, genre, publisher, book_content) VALUES
('Pride and Prejudice', 'Jane Austen', 1813, 'Classic', 'T. Egerton', 'Elizabeth Bennet, the sharp-witted second of five sisters in the English gentry, clashes with the wealthy, reserved Mr. Darcy after a series of misunderstandings and wounded pride. As their families and society weigh in on questions of marriage, money, and reputation, both must overcome their own prejudices to recognize their growing affection for one another.'),
('Sense and Sensibility', 'Jane Austen', 1811, 'Classic', 'T. Egerton', 'Sisters Elinor and Marianne Dashwood, left with little fortune after their father''s death, navigate romance and heartbreak with very different temperaments — Elinor''s practical restraint against Marianne''s passionate impulsiveness — as they search for security and love in Regency society.'),
('Emma', 'Jane Austen', 1815, 'Classic', 'John Murray', 'Wealthy, clever, and a little too confident in her own judgment, Emma Woodhouse amuses herself by matchmaking for her friends in a small English village, with comic and sometimes painful results as she slowly learns to see her own heart clearly.'),
('Frankenstein', 'Mary Shelley', 1818, 'Horror', 'Lackington, Hughes', 'Obsessed with conquering death, young scientist Victor Frankenstein assembles and animates a creature from dead tissue, only to abandon it in horror. Rejected by his creator and by society, the being turns to vengeance, setting off a tragic pursuit across Europe.'),
('Dracula', 'Bram Stoker', 1897, 'Horror', 'Archibald Constable and Company', 'When a young English solicitor travels to Transylvania to finalize a property deal, he discovers his client Count Dracula is a centuries-old vampire. As Dracula journeys to England to spread his curse, a small band of friends races to stop him before he claims more victims.'),
('Moby-Dick', 'Herman Melville', 1851, 'Classic', 'Harper & Brothers', 'Sailor Ishmael joins the whaling ship Pequod, commanded by the obsessive Captain Ahab, who is bent on hunting down Moby Dick, the great white whale that once cost him his leg. The voyage becomes a meditation on fate, obsession, and humanity''s struggle against nature.'),
('War and Peace', 'Leo Tolstoy', 1869, 'Classic', 'The Russian Messenger', 'Spanning the Napoleonic Wars, this sprawling epic follows several aristocratic Russian families — including the idealistic Pierre, the proud Prince Andrei, and the spirited Natasha — as love, war, and social upheaval reshape their lives and their nation.'),
('Anna Karenina', 'Leo Tolstoy', 1877, 'Classic', 'The Russian Messenger', 'Trapped in a loveless marriage, Anna Karenina risks scandal and ruin for a passionate affair with the dashing Count Vronsky, while the novel''s other central figure, Levin, searches for meaning through work, faith, and love in the Russian countryside.'),
('Crime and Punishment', 'Fyodor Dostoevsky', 1866, 'Classic', 'The Russian Messenger', 'Impoverished former student Raskolnikov murders a pawnbroker, convinced he is exempt from ordinary morality. As guilt and paranoia consume him, he is drawn toward confession and redemption under the watchful eye of a patient investigator.'),
('The Brothers Karamazov', 'Fyodor Dostoevsky', 1880, 'Classic', 'The Russian Messenger', 'The murder of a dissolute father implicates his three very different sons — one passionate, one intellectual, one devout — in a sweeping exploration of faith, doubt, free will, and morality in 19th-century Russia.'),
('The Great Gatsby', 'F. Scott Fitzgerald', 1925, 'Classic', 'Charles Scribner''s Sons', 'Narrator Nick Carraway is drawn into the glittering, reckless world of his mysterious neighbor Jay Gatsby, whose extravagant parties mask a desperate, obsessive love for the married Daisy Buchanan, exposing the hollowness beneath the glamour of the Jazz Age.'),
('The Adventures of Huckleberry Finn', 'Mark Twain', 1884, 'Adventure', 'Chatto & Windus', 'Escaping his abusive father, young Huck Finn rafts down the Mississippi River with Jim, an enslaved man fleeing for his freedom. Their journey becomes a sharp, often funny, sometimes searing look at friendship, conscience, and the moral failures of the society around them.'),
('The Adventures of Tom Sawyer', 'Mark Twain', 1876, 'Adventure', 'American Publishing Company', 'Mischievous Tom Sawyer schemes his way through small-town life along the Mississippi — tricking friends into whitewashing fences, running off to be a pirate, and stumbling into real danger when he and Huck witness a murder in the local graveyard.'),
('Alice''s Adventures in Wonderland', 'Lewis Carroll', 1865, 'Fantasy', 'Macmillan', 'After falling down a rabbit hole, young Alice finds herself in a nonsensical world of talking animals, a grinning Cheshire Cat, and a tyrannical Queen of Hearts, navigating riddles and absurd logic at every turn.'),
('Through the Looking-Glass', 'Lewis Carroll', 1871, 'Fantasy', 'Macmillan', 'Alice steps through a mirror into a mirrored world governed by the rules of chess, encountering Tweedledum and Tweedledee, the White Knight, and Humpty Dumpty on her way to becoming a queen.'),
('The Picture of Dorian Gray', 'Oscar Wilde', 1890, 'Classic', 'Ward, Lock and Company', 'A beautiful young man''s portrait ages and grows monstrous with every sin he commits, while he himself remains eternally youthful — until the painting''s hidden corruption can no longer be contained.'),
('Les Miserables', 'Victor Hugo', 1862, 'Classic', 'A. Lacroix, Verboeckhoven & Cie', 'Ex-convict Jean Valjean seeks redemption and a new life after his release, but is relentlessly pursued across decades of French history by the rigid police inspector Javert, even as he becomes a protector to the orphaned Cosette.'),
('The Count of Monte Cristo', 'Alexandre Dumas', 1844, 'Adventure', 'Pétion', 'Betrayed by jealous friends and wrongly imprisoned, sailor Edmond Dantès escapes after years in captivity, uncovers a hidden fortune, and reinvents himself to exact an elaborate, patient revenge on those who destroyed his life.'),
('The Three Musketeers', 'Alexandre Dumas', 1844, 'Adventure', 'Le Siecle', 'Young D''Artagnan travels to Paris to join the King''s Musketeers and quickly befriends Athos, Porthos, and Aramis. Together the four are drawn into court intrigue, duels, and a dangerous plot involving the Queen and the cunning Cardinal Richelieu.'),
('Treasure Island', 'Robert Louis Stevenson', 1883, 'Adventure', 'Cassell & Co', 'Young Jim Hawkins comes into possession of a map leading to buried pirate gold, and sets sail aboard the Hispaniola — only to discover that the ship''s cook, the one-legged Long John Silver, is secretly plotting mutiny.'),
('Strange Case of Dr Jekyll and Mr Hyde', 'Robert Louis Stevenson', 1886, 'Horror', 'Longmans, Green & Co.', 'A respected London doctor develops a potion that unleashes his darker impulses in the form of the violent, amoral Mr. Hyde, and finds himself increasingly unable to control which identity dominates.'),
('Robinson Crusoe', 'Daniel Defoe', 1719, 'Adventure', 'W. Taylor', 'Shipwrecked alone on a remote island, Robinson Crusoe spends decades building shelter, farming, and surviving through sheer resourcefulness, until the arrival of a native he names Friday changes his solitary existence.'),
('Around the World in Eighty Days', 'Jules Verne', 1873, 'Adventure', 'Pierre-Jules Hetzel', 'Wealthy Englishman Phileas Fogg wagers his entire fortune that he can circumnavigate the globe in just eighty days, racing against the clock by rail, steamship, and elephant while a detective wrongly suspects him of bank robbery.'),
('Twenty Thousand Leagues Under the Sea', 'Jules Verne', 1870, 'Science Fiction', 'Pierre-Jules Hetzel', 'A marine biologist is taken captive aboard the Nautilus, an advanced submarine commanded by the enigmatic Captain Nemo, and embarks on an underwater voyage past sunken ships, coral reefs, and the lost city of Atlantis.'),
('Journey to the Center of the Earth', 'Jules Verne', 1864, 'Science Fiction', 'Pierre-Jules Hetzel', 'Following clues in an old manuscript, a professor and his nephew descend through an Icelandic volcano into a vast subterranean world of prehistoric creatures and ancient seas.'),
('The War of the Worlds', 'H.G. Wells', 1898, 'Science Fiction', 'William Heinemann', 'Martian invaders land in the English countryside and unleash devastating heat-rays and poisonous gas, forcing a lone narrator to flee across a collapsing civilization as humanity''s technology proves helpless against the alien war machines.'),
('The Time Machine', 'H.G. Wells', 1895, 'Science Fiction', 'William Heinemann', 'An unnamed inventor builds a machine that carries him hundreds of thousands of years into the future, where he discovers humanity has split into two strange, degenerated descendant species living out the last days of Earth.'),
('The Invisible Man', 'H.G. Wells', 1897, 'Science Fiction', 'C. Arthur Pearson', 'A scientist discovers a method to render himself invisible, but finds the condition impossible to reverse, and descends into paranoia and violence as he tries to use his power to terrorize a small English village.'),
('Don Quixote', 'Miguel de Cervantes', 1605, 'Classic', 'Francisco de Robles', 'An aging nobleman, driven mad by chivalric romances, renames himself Don Quixote and sets out with his loyal squire Sancho Panza to revive knight-errantry, mistaking windmills for giants and inns for castles along the way.'),
('Little Women', 'Louisa May Alcott', 1868, 'Classic', 'Roberts Brothers', 'The four March sisters — ambitious Jo, gentle Beth, artistic Amy, and eldest Meg — grow up during and after the Civil War, navigating first loves, personal ambitions, and the bonds of sisterhood in genteel New England poverty.'),
('Anne of Green Gables', 'L.M. Montgomery', 1908, 'Classic', 'L.C. Page & Co.', 'An elderly brother and sister accidentally adopt an imaginative, talkative orphan girl instead of the boy they requested, and Anne Shirley''s boundless spirit slowly transforms their quiet farm and the whole town of Avonlea.'),
('The Secret Garden', 'Frances Hodgson Burnett', 1911, 'Classic', 'Frederick A. Stokes', 'Orphaned and sent to live at her uncle''s isolated manor, sickly and unpleasant Mary Lennox discovers a walled, forgotten garden — and in restoring it to life, begins to heal herself and her reclusive invalid cousin.'),
('A Study in Scarlet', 'Arthur Conan Doyle', 1887, 'Mystery', 'Ward Lock & Co', 'Dr. Watson, newly returned from war, moves in with the eccentric consulting detective Sherlock Holmes, and the two are drawn into their first case together: a baffling London murder with a single cryptic word written in blood.'),
('The Adventures of Sherlock Holmes', 'Arthur Conan Doyle', 1892, 'Mystery', 'George Newnes', 'A collection of twelve short mysteries showcasing Sherlock Holmes''s powers of deduction, from a stolen royal photograph to a mysterious band of speckled cord, as narrated by his loyal companion Dr. Watson.'),
('The Hound of the Baskervilles', 'Arthur Conan Doyle', 1902, 'Mystery', 'George Newnes', 'A supposed family curse and a legendary spectral hound threaten the last heir of Baskerville Hall, drawing Sherlock Holmes and Watson into a fog-bound Dartmoor mystery involving escaped convicts and a decades-old vendetta.'),
('A Tale of Two Cities', 'Charles Dickens', 1859, 'Historical Fiction', 'Chapman & Hall', 'Set against the backdrop of the French Revolution, the lives of a French doctor''s family and an dissolute English lawyer become entangled in Paris and London, building to an act of quiet, ultimate sacrifice.'),
('Great Expectations', 'Charles Dickens', 1861, 'Classic', 'Chapman & Hall', 'Orphaned blacksmith''s apprentice Pip receives a mysterious fortune from an anonymous benefactor and is whisked into London society, where he must reconcile his new ''great expectations'' with the people and values he left behind.'),
('Oliver Twist', 'Charles Dickens', 1837, 'Classic', 'Richard Bentley', 'An orphan born in a workhouse escapes to London only to fall in with a gang of child pickpockets led by the cunning Fagin, as questions of his true parentage and a chance at a better life slowly emerge.'),
('A Christmas Carol', 'Charles Dickens', 1843, 'Classic', 'Chapman & Hall', 'Miserly, friendless Ebenezer Scrooge is visited on Christmas Eve by the ghost of his late business partner and three spirits of Christmas past, present, and yet to come, who force him to confront the life he has wasted.'),
('The Wonderful Wizard of Oz', 'L. Frank Baum', 1900, 'Fantasy', 'George M. Hill Company', 'A tornado sweeps young Dorothy and her dog Toto from Kansas to the magical land of Oz, where she follows a road of yellow brick with new friends — a Scarecrow, a Tin Woodman, and a Cowardly Lion — to find the wizard who can send her home.'),
('Peter Pan', 'J.M. Barrie', 1911, 'Fantasy', 'Hodder & Stoughton', 'The Darling children fly off with the eternally young Peter Pan to Neverland, an island of pirates, mermaids, and lost boys, where growing up is the one thing forbidden.'),
('The Call of the Wild', 'Jack London', 1903, 'Adventure', 'Macmillan', 'Stolen from his comfortable home and sold into the brutal world of Alaskan sled dogs during the Klondike Gold Rush, the dog Buck gradually casts off civilization and answers the call of his wild ancestry.'),
('White Fang', 'Jack London', 1906, 'Adventure', 'Macmillan', 'Part wolf, part dog, White Fang is shaped by the harsh wilderness and cruel masters of the Yukon before finally finding kindness — and learning, in turn, how to give it.'),
('Wuthering Heights', 'Emily Bronte', 1847, 'Classic', 'Thomas Cautley Newby', 'The turbulent, destructive love between Heathcliff and Catherine Earnshaw echoes across two generations on the wild Yorkshire moors, consuming everyone drawn into its orbit.'),
('Jane Eyre', 'Charlotte Bronte', 1847, 'Classic', 'Smith, Elder & Co.', 'Orphaned and mistreated as a child, Jane Eyre becomes a governess at Thornfield Hall, where she falls for her brooding employer Mr. Rochester — only to uncover a devastating secret locked away in the house''s attic.'),
('The Scarlet Letter', 'Nathaniel Hawthorne', 1850, 'Classic', 'Ticknor, Reed & Fields', 'In Puritan Boston, Hester Prynne is publicly shamed and forced to wear a scarlet ''A'' for adultery, while the true identity of her child''s father festers in secret, slowly destroying him from within.'),
('The Metamorphosis', 'Franz Kafka', 1915, 'Classic', 'Kurt Wolff Verlag', 'Traveling salesman Gregor Samsa wakes one morning transformed into a giant insect, and must watch as his family''s initial concern curdles into shame, resentment, and neglect.'),
('The Odyssey', 'Homer', -800, 'Classic', 'Public Domain', 'After the fall of Troy, the cunning warrior Odysseus spends ten years struggling to return home to Ithaca, facing monsters, gods, and temptations, while his wife Penelope fends off suitors who believe him dead.'),
('Romeo and Juliet', 'William Shakespeare', 1597, 'Classic', 'Public Domain', 'Two teenagers from feuding families in Verona fall instantly and fatally in love, and their secret marriage sets off a chain of misunderstandings and violence that ends in the deaths of them both.'),
('Hamlet', 'William Shakespeare', 1603, 'Classic', 'Public Domain', 'Prince Hamlet of Denmark is visited by his father''s ghost, who reveals he was murdered by Hamlet''s uncle — now king and married to Hamlet''s mother — setting Hamlet on a spiraling path of doubt, feigned madness, and revenge.'),
('Grimms'' Fairy Tales', 'Jacob and Wilhelm Grimm', 1812, 'Fantasy', 'Realschulbuchhandlung', 'A collection of classic German folk tales gathered by the Brothers Grimm, including Cinderella, Snow White, Hansel and Gretel, and Rapunzel, in their darker, original forms.');
