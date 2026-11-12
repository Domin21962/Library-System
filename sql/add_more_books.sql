-- Adds more free, public-domain books to the catalog.
-- Run AFTER library_db.sql (the gutenberg_id and full_text columns must already exist; tools/import_texts.php creates them), then run:  php tools/import_texts.php
-- Safe to re-run: a book is only inserted if its title is not already in the catalog.

USE library_db;


INSERT INTO book (title, author, year, genre, publisher, book_content, gutenberg_id)
SELECT * FROM (
  SELECT 'Persuasion' AS title, 'Jane Austen' AS author, 1817 AS year, 'Classic' AS genre, 'John Murray' AS publisher, 'Anne Elliot, persuaded years ago to break her engagement to a naval officer, meets him again when he returns wealthy and successful.' AS book_content, 105 AS gutenberg_id
  UNION ALL
  SELECT 'Mansfield Park', 'Jane Austen', 1814, 'Classic', 'Thomas Egerton', 'Poor relation Fanny Price grows up in her wealthy uncle''s estate and holds to her principles amid scandal and temptation.', 141
  UNION ALL
  SELECT 'Northanger Abbey', 'Jane Austen', 1817, 'Classic', 'John Murray', 'Gothic-novel-loving Catherine Morland visits an old abbey and lets her imagination run away with her.', 121
  UNION ALL
  SELECT 'Middlemarch', 'George Eliot', 1871, 'Classic', 'William Blackwood', 'Interwoven lives in a provincial English town, centred on idealistic Dorothea Brooke and ambitious doctor Tertius Lydgate.', 145
  UNION ALL
  SELECT 'Vanity Fair', 'William Makepeace Thackeray', 1848, 'Classic', 'Bradbury and Evans', 'Clever, social-climbing Becky Sharp schemes her way through Regency society, contrasted with gentle Amelia Sedley.', 599
  UNION ALL
  SELECT 'David Copperfield', 'Charles Dickens', 1850, 'Classic', 'Bradbury and Evans', 'The life of David Copperfield from an unhappy childhood to success as a writer, with a vivid cast of friends and villains.', 766
  UNION ALL
  SELECT 'Hard Times', 'Charles Dickens', 1854, 'Classic', 'Bradbury and Evans', 'A satire of industrial Coketown where a man devoted to facts raises his children to distrust imagination.', 786
  UNION ALL
  SELECT 'Bleak House', 'Charles Dickens', 1853, 'Classic', 'Bradbury and Evans', 'A sprawling tale of a never-ending lawsuit, hidden family secrets and a murder in Victorian London.', 1023
  UNION ALL
  SELECT 'The Pickwick Papers', 'Charles Dickens', 1837, 'Classic', 'Chapman and Hall', 'Mr. Pickwick and his friends travel England in a series of comic adventures and misadventures.', 580
  UNION ALL
  SELECT 'Tess of the d''Urbervilles', 'Thomas Hardy', 1891, 'Classic', 'James R. Osgood', 'A poor country girl''s life is shaped by a cruel twist of fate, class prejudice and a rigid moral code.', 110
  UNION ALL
  SELECT 'The Mayor of Casterbridge', 'Thomas Hardy', 1886, 'Classic', 'Smith, Elder & Co.', 'A man who sells his wife in a drunken moment rises to be mayor, only to be undone by his past.', 143
  UNION ALL
  SELECT 'Heart of Darkness', 'Joseph Conrad', 1899, 'Classic', 'Blackwood''s Magazine', 'Sailor Marlow journeys up the Congo to find the enigmatic Kurtz and confronts the darkness of colonialism.', 219
  UNION ALL
  SELECT 'The Jungle Book', 'Rudyard Kipling', 1894, 'Adventure', 'Macmillan', 'Mowgli, a boy raised by wolves, learns the law of the jungle with Baloo and Bagheera while evading the tiger Shere Khan.', 236
  UNION ALL
  SELECT 'Gulliver''s Travels', 'Jonathan Swift', 1726, 'Adventure', 'Benjamin Motte', 'Lemuel Gulliver visits Lilliput, Brobdingnag and other strange lands in a sharp satire of human society.', 829
  UNION ALL
  SELECT 'The Importance of Being Earnest', 'Oscar Wilde', 1895, 'Drama', 'Leonard Smithers', 'Two gentlemen invent fictitious identities to escape their obligations, with farcical consequences.', 844
  UNION ALL
  SELECT 'Dubliners', 'James Joyce', 1914, 'Classic', 'Grant Richards', 'Fifteen short stories capturing everyday life and quiet crises in early twentieth-century Dublin.', 2814
  UNION ALL
  SELECT 'The Phantom of the Opera', 'Gaston Leroux', 1910, 'Horror', 'Pierre Lafitte', 'A disfigured genius haunts the Paris Opera House and obsesses over a young soprano.', 175
  UNION ALL
  SELECT 'The Hunchback of Notre-Dame', 'Victor Hugo', 1831, 'Classic', 'Gosselin', 'Quasimodo, the bell-ringer of Notre-Dame, and the gypsy Esmeralda are caught in a tragic web of love and cruelty.', 2610
  UNION ALL
  SELECT 'Madame Bovary', 'Gustave Flaubert', 1857, 'Classic', 'Michel Levy Freres', 'A doctor''s bored wife seeks escape in romance and luxury, with ruinous results.', 2413
  UNION ALL
  SELECT 'Candide', 'Voltaire', 1759, 'Classic', 'Cramer', 'Young Candide is taught that this is the best of all possible worlds, then endures a series of disasters.', 19942
  UNION ALL
  SELECT 'Black Beauty', 'Anna Sewell', 1877, 'Classic', 'Jarrold and Sons', 'A horse narrates his life, from a happy youth to hard labour, showing the importance of kindness to animals.', 271
  UNION ALL
  SELECT 'Heidi', 'Johanna Spyri', 1881, 'Classic', 'Gebrueder Henninger', 'An orphan girl brings joy to her grandfather in the Swiss Alps and to a sick girl in the city.', 1448
  UNION ALL
  SELECT 'The Wind in the Willows', 'Kenneth Grahame', 1908, 'Fantasy', 'Methuen', 'Mole, Rat, Badger and the reckless Mr. Toad share riverbank adventures.', 289
  UNION ALL
  SELECT 'The Adventures of Pinocchio', 'Carlo Collodi', 1883, 'Fantasy', 'Felice Paggi', 'A wooden puppet who longs to be a real boy learns hard lessons about honesty and obedience.', 500
  UNION ALL
  SELECT 'Uncle Tom''s Cabin', 'Harriet Beecher Stowe', 1852, 'Classic', 'John P. Jewett', 'An enslaved man''s suffering and faith expose the brutality of slavery in the United States.', 203
  UNION ALL
  SELECT 'Walden', 'Henry David Thoreau', 1854, 'Philosophy', 'Ticknor and Fields', 'Thoreau reflects on two years of simple living in a cabin beside Walden Pond.', 205
  UNION ALL
  SELECT 'The Art of War', 'Sun Tzu', -500, 'Philosophy', 'Ancient China', 'An ancient Chinese treatise on strategy, leadership and winning conflicts.', 132
  UNION ALL
  SELECT 'The Republic', 'Plato', -375, 'Philosophy', 'Ancient Greece', 'Socrates and others discuss justice and the ideal city-state in a foundational work of Western philosophy.', 1497
  UNION ALL
  SELECT 'The Prince', 'Niccolo Machiavelli', 1532, 'Philosophy', 'Antonio Blado', 'A pragmatic handbook on how rulers can gain and keep political power.', 1232
  UNION ALL
  SELECT 'Utopia', 'Thomas More', 1516, 'Philosophy', 'Dirk Martens', 'A traveller describes an imaginary island society and prompts a critique of European politics.', 2130
  UNION ALL
  SELECT 'Paradise Lost', 'John Milton', 1667, 'Poetry', 'Samuel Simmons', 'An epic poem on the Fall of Man, from Satan''s rebellion to the expulsion from Eden.', 26
  UNION ALL
  SELECT 'The Divine Comedy', 'Dante Alighieri', 1320, 'Poetry', 'Italy', 'Dante travels through Hell, Purgatory and Paradise in a grand allegory of the soul''s journey to God.', 8800
  UNION ALL
  SELECT 'The Iliad', 'Homer', -750, 'Poetry', 'Ancient Greece', 'The wrath of Achilles during the final year of the Trojan War.', 6130
  UNION ALL
  SELECT 'Kidnapped', 'Robert Louis Stevenson', 1886, 'Adventure', 'Cassell & Co', 'Young David Balfour is kidnapped and swept into the Jacobite troubles of 18th-century Scotland.', 421
  UNION ALL
  SELECT 'King Solomon''s Mines', 'H. Rider Haggard', 1885, 'Adventure', 'Cassell & Co', 'Allan Quatermain leads an expedition into unexplored Africa in search of a legendary treasure.', 2166
  UNION ALL
  SELECT 'The Prisoner of Zenda', 'Anthony Hope', 1894, 'Adventure', 'Arrowsmith', 'An English gentleman who resembles a kidnapped king is drawn into a plot to hold the throne.', 95
  UNION ALL
  SELECT 'The Lost World', 'Arthur Conan Doyle', 1912, 'Science Fiction', 'Hodder & Stoughton', 'Professor Challenger leads an expedition to a South American plateau where prehistoric creatures survive.', 139
  UNION ALL
  SELECT 'The Sign of the Four', 'Arthur Conan Doyle', 1890, 'Mystery', 'Spencer Blackett', 'Sherlock Holmes investigates a stolen treasure and a mysterious death with Dr. Watson.', 2097
  UNION ALL
  SELECT 'The Memoirs of Sherlock Holmes', 'Arthur Conan Doyle', 1893, 'Mystery', 'George Newnes', 'Further Holmes cases, including Silver Blaze and the final confrontation with Professor Moriarty.', 834
  UNION ALL
  SELECT 'The Awakening', 'Kate Chopin', 1899, 'Classic', 'Herbert S. Stone', 'A wife and mother in Louisiana begins to challenge the roles society expects of her.', 160
  UNION ALL
  SELECT 'Ethan Frome', 'Edith Wharton', 1911, 'Classic', 'Charles Scribner''s Sons', 'A poor farmer in snowy New England is trapped between duty to his wife and love for another woman.', 4517
  UNION ALL
  SELECT 'The Turn of the Screw', 'Henry James', 1898, 'Horror', 'William Heinemann', 'A governess becomes convinced that the two children in her care are haunted by malevolent ghosts.', 209
) AS new_books
WHERE NOT EXISTS (SELECT 1 FROM book b WHERE b.title = new_books.title);
